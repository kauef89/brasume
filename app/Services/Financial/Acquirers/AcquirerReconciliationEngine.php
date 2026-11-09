<?php

namespace App\Services\Financial\Acquirers;

use App\Enums\AcquirerMatchStatus;
use App\Models\BankAccount;
use App\Models\CashShift;
use App\Models\Device;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\AccountReceivable;
use App\Models\AcquirerTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcquirerReconciliationEngine
{
    /**
     * Processa a lista de DTOs do CSV, persiste no banco e tenta conciliar.
     */
    public function reconcile(array $transactions): array
    {
        $results = [
            'total_imported'     => 0,
            'matched'            => 0,
            'ambiguous'          => 0,
            'orphan'             => 0,
            'already_reconciled' => 0,
        ];

        $devicesByCode = Device::query()
            ->whereNotNull('terminal_code')
            ->get()
            ->keyBy('terminal_code');

        $matchedPaymentIdsThisRun = [];

        foreach ($transactions as $dto) {
            /** @var AcquirerTransactionDTO $dto */
            $cleanTid = strtoupper(str_replace([' ', '-', '_'], '', $dto->terminalCode));
            $device = $devicesByCode->get($dto->terminalCode)
                ?? $devicesByCode->first(function ($d) use ($cleanTid) {
                    $devTid = strtoupper(str_replace([' ', '-', '_'], '', $d->terminal_code ?? ''));
                    if ($devTid === $cleanTid) return true;
                    return str_replace('O', '0', $devTid) === str_replace('O', '0', $cleanTid);
                });

            $acquirerTx = AcquirerTransaction::firstOrCreate(
                [
                    'acquirer_name'    => $dto->acquirerName,
                    'terminal_code'    => $dto->terminalCode,
                    'nsu'              => $dto->nsu,
                    'transaction_date' => $dto->transactionDate,
                ],
                [
                    'device_id'                => $device?->id,
                    'modality'                 => $dto->modality,
                    'brand'                    => $dto->brand,
                    'gross_amount'             => $dto->grossAmount,
                    'fee_amount'               => $dto->feeAmount,
                    'net_amount'               => $dto->netAmount,
                    'authorization_code'       => $dto->authorizationCode,
                    'expected_settlement_date' => $dto->expectedSettlementDate,
                    'status'                   => AcquirerMatchStatus::ORPHAN,
                ]
            );

            $results['total_imported']++;

            if ($acquirerTx->status === AcquirerMatchStatus::MATCHED || $acquirerTx->status === AcquirerMatchStatus::IGNORED) {
                $results['already_reconciled']++;
                continue;
            }

            $scored = $this->findCandidatesFor($acquirerTx, $matchedPaymentIdsThisRun);

            if ($scored->isEmpty()) {
                $acquirerTx->update(['status' => AcquirerMatchStatus::ORPHAN]);
                $results['orphan']++;
                continue;
            }

            $topCandidate = $scored->first();
            $secondCandidate = $scored->get(1);

            $isTied = $secondCandidate && ($topCandidate['score'] - $secondCandidate['score']) < 15;
            $tiedCandidates = $scored->filter(fn ($c) => ($topCandidate['score'] - $c['score']) < 15);
            $isSameOrderTie = $tiedCandidates->pluck('order.id')->unique()->filter()->count() === 1;

            $isLowConfidence = $topCandidate['score'] < 45;

            // Se empatar com pedidos concorrentes ou score for baixo, trava em dúvida
            if (($scored->count() > 1 && $isTied && ! $isSameOrderTie) || $isLowConfidence) {
                $reason = $isLowConfidence
                    ? "Candidato encontrado com baixa confiança ({$topCandidate['score']} pts). Requer conferência."
                    : "Encontrados {$scored->count()} pedidos com valor idêntico no mesmo horário.";

                $acquirerTx->update([
                    'status' => AcquirerMatchStatus::AMBIGUOUS,
                    'notes'  => $reason,
                ]);
                $results['ambiguous']++;
                continue;
            }

            // Auto-Match aprovado
            /** @var Payment $matchedPayment */
            $matchedPayment = $topCandidate['payment'];
            $order = $topCandidate['order'];
            $matchedPaymentIdsThisRun[] = $matchedPayment->id;

            DB::transaction(function () use ($acquirerTx, $matchedPayment, $order, $dto, $device) {
                $deviceName = $device?->name ?? 'Maquininha';
                $desc = "Repasse " . strtoupper($dto->acquirerName) . " - " . strtoupper($dto->modality) . " (NSU {$dto->nsu} | {$deviceName})";

                $fallbackBankId = BankAccount::where('bank_name', 'like', '%sicredi%')->first()?->id 
                    ?? BankAccount::first()?->id;

                $receivable = AccountReceivable::create([
                    'order_id'        => $order?->id,
                    'customer_id'     => $order?->customer_id,
                    'bank_account_id' => $matchedPayment->paymentMethod?->destination_account_id ?? $fallbackBankId,
                    'description'     => $desc,
                    'gross_amount'    => $dto->grossAmount,
                    'fee_amount'      => $dto->feeAmount,
                    'net_amount'      => $dto->netAmount,
                    'due_date'        => $dto->expectedSettlementDate,
                    'status'          => 'pending',
                ]);

                $acquirerTx->update([
                    'status'                => AcquirerMatchStatus::MATCHED,
                    'order_id'              => $order?->id,
                    'payment_id'            => $matchedPayment->id,
                    'account_receivable_id' => $receivable->id,
                    'notes'                 => null,
                ]);

                if ($matchedPayment->transaction) {
                    $matchedPayment->transaction->update([
                        'description' => $matchedPayment->transaction->description . " | NSU: {$dto->nsu}"
                    ]);
                }
            });

            $results['matched']++;
        }

        return $results;
    }

    /**
     * Encontra e pontua candidatos aplicando a regra de Caixa Master e desempate sequencial.
     */
    public function findCandidatesFor(AcquirerTransaction $acquirerTx, array $excludePaymentIds = []): Collection
    {
        $windowStart = $acquirerTx->transaction_date->copy()->subMinutes(45);
        $windowEnd   = $acquirerTx->transaction_date->copy()->addMinutes(45);

        // 1. Janela operacional de 45 minutos
        $query = Payment::query()
            ->with(['order.deliveryAddress', 'order.customer', 'order.items.product', 'order.payments.paymentMethod', 'paymentMethod'])
            ->where('amount', $acquirerTx->gross_amount)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereDoesntHave('acquirerTransaction', fn ($q) => $q->where('status', AcquirerMatchStatus::MATCHED))
            ->whereDoesntHave('paymentMethod', fn ($q) => $q->where('engine_type', \App\Enums\PaymentEngineType::PIX_STATIC->value)->orWhere('id', 13))
            ->when(!empty($excludePaymentIds), fn ($q) => $q->whereNotIn('id', $excludePaymentIds));

        $candidates = $query->get();

        // 2. Fallback por data do dia
        if ($candidates->isEmpty()) {
            $candidates = Payment::query()
                ->with(['order.deliveryAddress', 'order.customer', 'order.items.product', 'order.payments.paymentMethod', 'paymentMethod'])
                ->where('amount', $acquirerTx->gross_amount)
                ->whereDate('created_at', $acquirerTx->transaction_date->toDateString())
                ->whereDoesntHave('acquirerTransaction', fn ($q) => $q->where('status', AcquirerMatchStatus::MATCHED))
            ->whereDoesntHave('paymentMethod', fn ($q) => $q->where('engine_type', \App\Enums\PaymentEngineType::PIX_STATIC->value)->orWhere('id', 13))
                ->when(!empty($excludePaymentIds), fn ($q) => $q->whereNotIn('id', $excludePaymentIds))
                ->get();
        }

        $device = $acquirerTx->device;
        $rawModality = str_replace(['?', ''], 'e', mb_strtolower($acquirerTx->modality ?? ''));
        $txModality = Str::ascii($rawModality);

        return $candidates->map(function ($payment) use ($acquirerTx, $device, $txModality) {
            $score = 0;
            $order = $payment->order;
            $scope = $device?->assignment_scope;

            // 🎯 A. Correspondência de Escopo (Regra CAIXA MASTER) (Até 30 pts)
            if ($scope === 'counter') {
                // Caixa Fixo atende Balcão, Retirada e Mesas com pontuação integral
                if (in_array($order?->type, ['counter', 'takeaway', 'table'])) {
                    $score += 30;
                } elseif ($order?->type === 'delivery') {
                    $score += 10;
                }
            } elseif ($scope === 'waiter') {
                // Maquininha volante de garçom exclusiva para mesas
                if ($order?->type === 'table') {
                    $score += 30;
                }
            } elseif ($scope === 'delivery') {
                // Maquininha exclusiva de motoboy
                if ($order?->type === 'delivery') {
                    $score += 30;
                }
            } elseif ($scope === 'floating') {
                // Coringa / Reserva
                $score += 25;
            } else {
                $score += 20;
            }

            // 🎯 B. Vínculo de Usuário / Entregador (Até 20 pts)
            if ($device?->default_user_id) {
                $courierUserId = $order?->courier?->user_id ?? $order?->courier?->user?->id ?? $order?->driver_id;
                if ($scope === 'delivery' && $courierUserId === $device->default_user_id) {
                    $score += 20;
                } elseif (in_array($scope, ['counter', 'waiter']) && $order?->user_id === $device->default_user_id) {
                    $score += 20;
                }
            }

            // 🎯 C. Proximidade de Tempo Inteligente (Até 30 pts)
            $diffOrder  = $order?->created_at ? abs($acquirerTx->transaction_date->diffInSeconds($order->created_at)) : 999999;
            $diffClosed = $order?->closed_at ? abs($acquirerTx->transaction_date->diffInSeconds($order->closed_at)) : 999999;
            $diffPay    = $payment->created_at ? abs($acquirerTx->transaction_date->diffInSeconds($payment->created_at)) : 999999;

            $diffSeconds = min($diffOrder, $diffClosed, $diffPay);

            if ($diffSeconds <= 180) {        // Até 3 min
                $score += 30;
            } elseif ($diffSeconds <= 600) {  // Até 10 min
                $score += 25;
            } elseif ($diffSeconds <= 1800) { // Até 30 min
                $score += 15;
            } elseif ($diffSeconds <= 3600) { // Até 1 hora
                $score += 10;
            }

            // 🎯 D. Modalidade de Pagamento (Até 20 pts)
            $methodName = Str::ascii(mb_strtolower($payment->method ?? $payment->paymentMethod?->name ?? ''));
            $isDebit = str_contains($txModality, 'deb') && str_contains($methodName, 'deb');
            $isCredit = str_contains($txModality, 'cred') && str_contains($methodName, 'cred');
            $isPix = (str_contains($txModality, 'pix') || str_contains($txModality, 'qrcode')) && str_contains($methodName, 'pix');
            $isVoucher = str_contains($txModality, 'voucher') && (str_contains($methodName, 'refei') || str_contains($methodName, 'aliment'));

            if ($isDebit || $isCredit || $isPix || $isVoucher) {
                $score += 20;
            } elseif (str_contains($methodName, 'cart') || ($payment->paymentMethod?->engine_type?->value ?? '') === 'manual') {
                $score += 10;
            } elseif (str_contains($methodName, 'dinheiro') || str_contains($methodName, 'cash')) {
                $score += 5;
            }

            return [
                'payment' => $payment,
                'order'   => $order,
                'score'   => min(100, $score),
            ];
        })
        // Desempate intra-pedido: ordena por score e depois por ID de pagamento sequencial
        ->sort(function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['payment']->id <=> $b['payment']->id;
            }
            return $b['score'] <=> $a['score'];
        })
        ->values();
    }

    /**
     * Vínculo manual com conversão operacional de meios de pagamento
     */
    public function linkManually(AcquirerTransaction $acquirerTx, Order $order, ?Payment $payment = null): void
    {
        DB::transaction(function () use ($acquirerTx, $order, $payment) {
            $device = $acquirerTx->device;
            $deviceName = $device?->name ?? 'Maquininha';
            $desc = "Repasse " . strtoupper($acquirerTx->acquirer_name) . " - " . strtoupper($acquirerTx->modality) . " (NSU {$acquirerTx->nsu} | {$deviceName})";

            $fallbackBankId = BankAccount::where('bank_name', 'like', '%sicredi%')->first()?->id 
                ?? BankAccount::first()?->id;

            if ($payment) {
                $methodName = mb_strtolower($payment->method ?? '');
                $isCash = str_contains($methodName, 'dinheiro') || str_contains($methodName, 'cash');

                if ($isCash) {
                    $targetMethod = $this->resolvePaymentMethodForModality($acquirerTx->modality);
                    if ($targetMethod) {
                        $payment->update([
                            'payment_method_id' => $targetMethod->id,
                            'method'            => $targetMethod->name,
                        ]);

                        if ($payment->transaction) {
                            $payment->transaction->update([
                                'payment_method_id' => $targetMethod->id,
                                'bank_account_id'   => $targetMethod->destination_account_id ?? $fallbackBankId,
                                'description'       => "Pedido #{$order->id} ({$targetMethod->name}) | NSU {$acquirerTx->nsu}",
                            ]);
                        }
                    }
                }
            }

            $receivable = AccountReceivable::create([
                'order_id'        => $order->id,
                'customer_id'     => $order->customer_id,
                'bank_account_id' => $payment?->paymentMethod?->destination_account_id ?? $fallbackBankId,
                'description'     => $desc,
                'gross_amount'    => $acquirerTx->gross_amount,
                'fee_amount'      => $acquirerTx->fee_amount,
                'net_amount'      => $acquirerTx->net_amount,
                'due_date'        => $acquirerTx->expected_settlement_date,
                'status'          => 'pending',
            ]);

            $acquirerTx->update([
                'status'                => AcquirerMatchStatus::MATCHED,
                'order_id'              => $order->id,
                'payment_id'            => $payment?->id,
                'account_receivable_id' => $receivable->id,
                'notes'                 => 'Vinculado manualmente pelo operador.',
            ]);

            if ($payment?->transaction && !str_contains($payment->transaction->description, $acquirerTx->nsu)) {
                $payment->transaction->update([
                    'description' => $payment->transaction->description . " | NSU: {$acquirerTx->nsu}"
                ]);
            }
        });
    }

    /**
     * Reavalia todas as transações com dúvida ou órfãs.
     */
    public function reprocessPending(): int
    {
        $pending = AcquirerTransaction::whereIn('status', [
            AcquirerMatchStatus::AMBIGUOUS,
            AcquirerMatchStatus::ORPHAN,
        ])->orderBy('transaction_date', 'asc')->get();

        $promoted = 0;
        $matchedPaymentIdsThisRun = [];

        foreach ($pending as $tx) {
            $candidates = $this->findCandidatesFor($tx, $matchedPaymentIdsThisRun);
            if ($candidates->isEmpty()) continue;

            $top = $candidates->first();
            $second = $candidates->get(1);

            $isTied = $second && ($top['score'] - $second['score']) < 15;
            $tiedCandidates = $candidates->filter(fn ($c) => ($top['score'] - $c['score']) < 15);
            $isSameOrderTie = $tiedCandidates->pluck('order.id')->unique()->filter()->count() === 1;

            if ($top['score'] >= 45 && (! $isTied || $isSameOrderTie)) {
                $this->linkManually($tx, $top['order'], $top['payment']);
                $matchedPaymentIdsThisRun[] = $top['payment']->id;
                $promoted++;
            }
        }

        return $promoted;
    }

    public function createRetroactiveOrder(AcquirerTransaction $acquirerTx, ?string $observation = null): Order
    {
        return DB::transaction(function () use ($acquirerTx, $observation) {
            $notes = "Venda avulsa criada via conciliação de maquininha (NSU {$acquirerTx->nsu}).";
            if ($observation) {
                $notes .= " Obs: {$observation}";
            }

            $shift = CashShift::where('opened_at', '<=', $acquirerTx->transaction_date)
                ->where(function ($q) use ($acquirerTx) {
                    $q->whereNull('closed_at')
                      ->orWhere('closed_at', '>=', $acquirerTx->transaction_date);
                })
                ->latest('id')
                ->first();

            $order = Order::create([
                'type'           => 'counter',
                'source'         => Order::SOURCE_CASHIER,
                'status'         => 'delivered',
                'payment_status' => 'paid',
                'total'          => $acquirerTx->gross_amount,
                'subtotal'       => $acquirerTx->gross_amount,
                'cash_shift_id'  => $shift?->id,
                'notes'          => $notes,
                'created_at'     => $acquirerTx->transaction_date,
                'updated_at'     => $acquirerTx->transaction_date,
            ]);

            $paymentMethod = $this->resolvePaymentMethodForModality($acquirerTx->modality);

            $payment = Payment::create([
                'order_id'          => $order->id,
                'cash_shift_id'     => $shift?->id,
                'payment_method_id' => $paymentMethod?->id,
                'method'            => $paymentMethod?->name ?? 'Cartão',
                'amount'            => $acquirerTx->gross_amount,
                'status'            => 'completed',
                'is_paid'           => true,
                'created_at'        => $acquirerTx->transaction_date,
                'updated_at'        => $acquirerTx->transaction_date,
            ]);

            $this->linkManually($acquirerTx, $order, $payment);

            return $order;
        });
    }

    public function ignore(AcquirerTransaction $acquirerTx): void
    {
        $acquirerTx->update([
            'status' => AcquirerMatchStatus::IGNORED,
            'notes'  => 'Marcado como ignorado pelo operador.',
        ]);
    }

    protected function resolvePaymentMethodForModality(?string $modality): ?PaymentMethod
    {
        $m = Str::ascii(mb_strtolower($modality ?? ''));

        if (str_contains($m, 'deb')) {
            return PaymentMethod::where('name', 'like', '%deb%')->first()
                ?? PaymentMethod::find(5);
        }

        if (str_contains($m, 'cred')) {
            return PaymentMethod::where('name', 'like', '%cred%')->first()
                ?? PaymentMethod::find(4);
        }

        if (str_contains($m, 'pix') || str_contains($m, 'qrcode')) {
            return PaymentMethod::where('engine_type', 'pix_machine')->first()
                ?? PaymentMethod::where('name', 'like', '%pix%')->first();
        }

        if (str_contains($m, 'voucher')) {
            return PaymentMethod::where('engine_type', 'voucher')->first()
                ?? PaymentMethod::where('name', 'like', '%voucher%')->first();
        }

        return PaymentMethod::whereIn('engine_type', ['smart_pos', 'manual', 'tef'])->first();
    }

    /**
     * Localiza pares de pagamentos pendentes que somados fecham exatamente o valor da filipeta.
     */
    public function findBundleCandidatesFor(AcquirerTransaction $acquirerTx): Collection
    {
        $windowStart = $acquirerTx->transaction_date->copy()->subMinutes(90);
        $windowEnd   = $acquirerTx->transaction_date->copy()->addMinutes(90);

        $pendingPayments = Payment::with('order')
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->where('amount', '<', $acquirerTx->gross_amount)
            ->whereDoesntHave('acquirerTransaction', fn ($q) => $q->where('status', AcquirerMatchStatus::MATCHED))
            ->whereDoesntHave('paymentMethod', fn ($q) => $q->where('engine_type', \App\Enums\PaymentEngineType::PIX_STATIC->value)->orWhere('id', 13))
            ->orderBy('created_at', 'asc')
            ->get();

        $bundles = collect();
        $target = (float) $acquirerTx->gross_amount;
        $count = $pendingPayments->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $p1 = $pendingPayments[$i];
                $p2 = $pendingPayments[$j];

                if (abs(((float)$p1->amount + (float)$p2->amount) - $target) < 0.02) {
                    $bundles->push([
                        'payments' => collect([$p1, $p2]),
                        'orders'   => collect([$p1->order, $p2->order])->filter(),
                        'sum'      => (float)$p1->amount + (float)$p2->amount,
                    ]);
                }
            }
        }

        return $bundles;
    }
}