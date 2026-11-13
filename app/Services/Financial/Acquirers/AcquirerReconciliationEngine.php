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

            // FASE 1: Auto-Match 1:1
            $scored = $this->findCandidatesFor($acquirerTx, $matchedPaymentIdsThisRun);

            if ($scored->isNotEmpty()) {
                $topCandidate = $scored->first();
                $secondCandidate = $scored->get(1);

                $isTied = $secondCandidate && ($topCandidate['score'] - $secondCandidate['score']) < 15;
                $tiedCandidates = $scored->filter(fn ($c) => ($topCandidate['score'] - $c['score']) < 15);
                $isSameOrderTie = $tiedCandidates->pluck('order.id')->unique()->filter()->count() === 1;

                $isLowConfidence = $topCandidate['score'] < 45;

                if (! (($scored->count() > 1 && $isTied && ! $isSameOrderTie) || $isLowConfidence)) {
                    $matchedPayment = $topCandidate['payment'];
                    $order = $topCandidate['order'];
                    $matchedPaymentIdsThisRun[] = $matchedPayment->id;

                    $this->linkSingle($acquirerTx, $order, $matchedPayment);
                    $results['matched']++;
                    continue;
                }
            }

            // FASE 2: Auto-Match em Grupo 1:N (Mesma Mesa / Cliente / Subset-Sum)
            $bundles = $this->findBundleCandidatesFor($acquirerTx, $matchedPaymentIdsThisRun);

            if ($bundles->isNotEmpty()) {
                $bestBundle = $bundles->first();
                if ($bestBundle['confidence'] >= 70) {
                    $this->linkBundle($acquirerTx, $bestBundle['payments'], $bestBundle['reason']);
                    foreach ($bestBundle['payments'] as $bp) {
                        $matchedPaymentIdsThisRun[] = $bp->id;
                    }
                    $results['matched']++;
                    continue;
                }
            }

            // Se não encontrou nem 1:1 nem 1:N seguro
            if ($scored->isNotEmpty()) {
                $acquirerTx->update([
                    'status' => AcquirerMatchStatus::AMBIGUOUS,
                    'notes'  => "Encontrados pedidos concorrentes no mesmo horário. Requer conferência.",
                ]);
                $results['ambiguous']++;
            } else {
                $acquirerTx->update(['status' => AcquirerMatchStatus::ORPHAN]);
                $results['orphan']++;
            }
        }

        return $results;
    }

    /**
     * Realiza a vinculação 1:1 de pagamento com a transação da adquirente
     */
    public function linkSingle(AcquirerTransaction $acquirerTx, ?Order $order, Payment $matchedPayment): void
    {
        DB::transaction(function () use ($acquirerTx, $order, $matchedPayment) {
            $device = $acquirerTx->device;
            $deviceName = $device?->name ?? 'Maquininha';
            $desc = "Repasse " . strtoupper($acquirerTx->acquirer_name) . " - " . strtoupper($acquirerTx->modality) . " (NSU {$acquirerTx->nsu} | {$deviceName})";

            $fallbackBankId = BankAccount::where('bank_name', 'like', '%sicredi%')->first()?->id 
                ?? BankAccount::first()?->id;

            $netAmount = (float) $acquirerTx->net_amount > 0
                ? (float) $acquirerTx->net_amount
                : round((float) $acquirerTx->gross_amount - (float) $acquirerTx->fee_amount, 2);

            $receivable = ($acquirerTx->account_receivable_id ? AccountReceivable::find($acquirerTx->account_receivable_id) : null)
                ?? AccountReceivable::create([
                    'order_id'        => $order?->id,
                    'customer_id'     => $order?->customer_id,
                    'bank_account_id' => $matchedPayment->paymentMethod?->destination_account_id ?? $fallbackBankId,
                    'description'     => $desc,
                    'gross_amount'    => $acquirerTx->gross_amount,
                    'fee_amount'      => $acquirerTx->fee_amount,
                    'net_amount'      => $netAmount,
                    'due_date'        => $acquirerTx->expected_settlement_date,
                    'status'          => 'pending',
                ]);

            $acquirerTx->update([
                'status'                => AcquirerMatchStatus::MATCHED,
                'reconciliation_source' => 'auto',
                'reconciled_by_name'    => 'Motor Automático',
                'reconciled_at'         => now(),
                'order_id'              => $order?->id,
                'payment_id'            => $matchedPayment->id,
                'account_receivable_id' => $receivable->id,
                'notes'                 => null,
            ]);

            $matchedPayment->update([
                'acquirer_transaction_id' => $acquirerTx->id,
            ]);

            if ($matchedPayment->transaction) {
                $matchedPayment->transaction->update([
                    'description' => $matchedPayment->transaction->description . " | NSU: {$acquirerTx->nsu}"
                ]);
            }
        });
    }

    /**
     * Realiza a vinculação 1:N de múltiplos pedidos agrupados em uma única passada de máquina
     */
    public function linkBundle(AcquirerTransaction $acquirerTx, Collection $payments, string $reason): void
    {
        DB::transaction(function () use ($acquirerTx, $payments, $reason) {
            $leadPayment = $payments->first();
            $leadOrder   = $leadPayment?->order;
            $device      = $acquirerTx->device;
            $deviceName  = $device?->name ?? 'Maquininha';

            $orderIdsStr = $payments->pluck('order_id')->filter()->unique()->map(fn($id) => "#{$id}")->implode(', ');
            $desc = "Repasse " . strtoupper($acquirerTx->acquirer_name) . " - " . strtoupper($acquirerTx->modality) . " (NSU {$acquirerTx->nsu} | Agrupamento {$orderIdsStr})";

            $fallbackBankId = BankAccount::where('bank_name', 'like', '%sicredi%')->first()?->id 
                ?? BankAccount::first()?->id;

            $receivable = AccountReceivable::create([
                'order_id'        => $leadOrder?->id,
                'customer_id'     => $leadOrder?->customer_id,
                'bank_account_id' => $leadPayment?->paymentMethod?->destination_account_id ?? $fallbackBankId,
                'description'     => $desc,
                'gross_amount'    => $acquirerTx->gross_amount,
                'fee_amount'      => $acquirerTx->fee_amount,
                'net_amount'      => $acquirerTx->net_amount,
                'due_date'        => $acquirerTx->expected_settlement_date,
                'status'          => 'pending',
            ]);

            $acquirerTx->update([
                'status'                => AcquirerMatchStatus::MATCHED,
                'reconciliation_source' => 'auto',
                'reconciled_by_name'    => 'Motor Automático',
                'reconciled_at'         => now(),
                'order_id'              => $leadOrder?->id,
                'payment_id'            => $leadPayment->id,
                'account_receivable_id' => $receivable->id,
                'notes'                 => "Conciliado em Lote (1:N): " . $reason,
            ]);

            // Atualiza todos os pagamentos do lote com o vínculo da transação
            foreach ($payments as $p) {
                $p->update([
                    'acquirer_transaction_id' => $acquirerTx->id,
                ]);

                if ($p->transaction && !str_contains($p->transaction->description, $acquirerTx->nsu)) {
                    $p->transaction->update([
                        'description' => $p->transaction->description . " | NSU: {$acquirerTx->nsu} (Agrupado)"
                    ]);
                }
            }
        });
    }

    /**
     * Encontra e pontua candidatos individuais 1:1
     */
    public function findCandidatesFor(AcquirerTransaction $acquirerTx, array $excludePaymentIds = []): Collection
    {
        $windowStart = $acquirerTx->transaction_date->copy()->subMinutes(45);
        $windowEnd   = $acquirerTx->transaction_date->copy()->addMinutes(45);

        $query = Payment::query()
            ->with(['order.deliveryAddress', 'order.customer', 'order.items.product', 'order.payments.paymentMethod', 'paymentMethod'])
            ->where('amount', $acquirerTx->gross_amount)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereNull('acquirer_transaction_id')
            ->whereDoesntHave('paymentMethod', fn ($q) => $q->where('engine_type', \App\Enums\PaymentEngineType::PIX_STATIC->value)->orWhere('id', 13))
            ->when(!empty($excludePaymentIds), fn ($q) => $q->whereNotIn('id', $excludePaymentIds));

        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            $candidates = Payment::query()
                ->with(['order.deliveryAddress', 'order.customer', 'order.items.product', 'order.payments.paymentMethod', 'paymentMethod'])
                ->where('amount', $acquirerTx->gross_amount)
                ->whereDate('created_at', $acquirerTx->transaction_date->toDateString())
                ->whereNull('acquirer_transaction_id')
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

            // Escopo
            if ($scope === 'counter') {
                if (in_array($order?->type, ['counter', 'takeaway', 'table', 'pickup'])) $score += 30;
                elseif ($order?->type === 'delivery') $score += 10;
            } elseif ($scope === 'waiter' && $order?->type === 'table') {
                $score += 30;
            } elseif ($scope === 'delivery' && $order?->type === 'delivery') {
                $score += 30;
            } else {
                $score += 20;
            }

            // Proximidade temporal
            $diffPay = $payment->created_at ? abs($acquirerTx->transaction_date->diffInSeconds($payment->created_at)) : 999999;
            if ($diffPay <= 180) $score += 30;
            elseif ($diffPay <= 600) $score += 25;
            elseif ($diffPay <= 1800) $score += 15;
            elseif ($diffPay <= 3600) $score += 10;
            
            // 🚀 Bônus de Turno (Shift-Awareness): Permite comandas fechadas mais tarde no mesmo turno
            $shift = $payment->order?->cash_shift_id ? \App\Models\CashShift::find($payment->order->cash_shift_id) : null;
            if ($shift && $shift->opened_at && $acquirerTx->transaction_date >= $shift->opened_at && $acquirerTx->transaction_date <= ($shift->closed_at ?? now())) {
                $score += 25;
            }

            // Modalidade
            $methodName = Str::ascii(mb_strtolower($payment->method ?? ''));
            if ((str_contains($txModality, 'deb') && str_contains($methodName, 'deb')) ||
                (str_contains($txModality, 'cred') && str_contains($methodName, 'cred')) ||
                (str_contains($txModality, 'pix') && str_contains($methodName, 'pix'))) {
                $score += 20;
            } else {
                $score += 5;
            }

            return [
                'payment' => $payment,
                'order'   => $order,
                'score'   => min(100, $score),
            ];
        })->sortByDesc('score')->values();
    }

    /**
     * Motor de Agrupamento Multipedidos 1:N (Subset-Sum inteligente)
     */
    public function findBundleCandidatesFor(AcquirerTransaction $acquirerTx, array $excludePaymentIds = []): Collection
    {
        $target = (float) $acquirerTx->gross_amount;
        $windowStart = $acquirerTx->transaction_date->copy()->subMinutes(120);
        $windowEnd   = $acquirerTx->transaction_date->copy()->addMinutes(120);

        // Busca pagamentos pendentes de cartão/pix máquina
        $pendingPayments = Payment::with('order')
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->where('amount', '<', $target)
            ->whereNull('acquirer_transaction_id')
            ->whereDoesntHave('paymentMethod', fn ($q) => $q->where('engine_type', \App\Enums\PaymentEngineType::PIX_STATIC->value)->orWhere('id', 13))
            ->when(!empty($excludePaymentIds), fn ($q) => $q->whereNotIn('id', $excludePaymentIds))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingPayments->count() < 2) {
            // Fallback para o dia inteiro
            $pendingPayments = Payment::with('order')
                ->whereDate('created_at', $acquirerTx->transaction_date->toDateString())
                ->where('amount', '<', $target)
                ->whereNull('acquirer_transaction_id')
                ->whereDoesntHave('paymentMethod', fn ($q) => $q->where('engine_type', \App\Enums\PaymentEngineType::PIX_STATIC->value)->orWhere('id', 13))
                ->when(!empty($excludePaymentIds), fn ($q) => $q->whereNotIn('id', $excludePaymentIds))
                ->orderBy('created_at', 'asc')
                ->get();
        }

        $bundles = collect();

        // 🎯 ESTRATÉGIA A: Agrupamento Prioritário por MESMA MESA
        $byTable = $pendingPayments->groupBy(fn($p) => trim(str_ireplace('mesa', '', (string)($p->order?->table_number ?? ''))));
        foreach ($byTable as $tableKey => $tablePayments) {
            if (empty($tableKey) || $tablePayments->count() < 2) continue;

            $subsets = $this->findSubsetsMatchingSum($tablePayments->values(), $target);
            foreach ($subsets as $subset) {
                $orderIds = $subset->pluck('order_id')->unique()->implode(', #');
                $bundles->push([
                    'payments'   => $subset,
                    'confidence' => 95, // Altíssima certeza: mesma mesa e soma matemática idêntica
                    'reason'     => "Mesa {$tableKey} ({$subset->count()} pedidos: #{$orderIds}) somam exatamente R$ " . number_format($target, 2, ',', '.'),
                ]);
            }
        }

        // 🎯 ESTRATÉGIA B: Agrupamento por MESMO CLIENTE (Delivery/Balcão)
        $byCustomer = $pendingPayments->groupBy(fn($p) => $p->order?->customer_id);
        foreach ($byCustomer as $customerId => $custPayments) {
            if (!$customerId || $custPayments->count() < 2) continue;

            $subsets = $this->findSubsetsMatchingSum($custPayments->values(), $target);
            foreach ($subsets as $subset) {
                $custName = $subset->first()->order?->customer_name ?? "Cliente #{$customerId}";
                $orderIds = $subset->pluck('order_id')->unique()->implode(', #');
                $bundles->push([
                    'payments'   => $subset,
                    'confidence' => 90,
                    'reason'     => "{$custName} ({$subset->count()} pedidos: #{$orderIds}) somam exatamente R$ " . number_format($target, 2, ',', '.'),
                ]);
            }
        }

        // 🎯 ESTRATÉGIA C: Agrupamento Geral por Janela Temporal (se não fechou em A ou B)
        if ($bundles->isEmpty() && $pendingPayments->count() >= 2 && $pendingPayments->count() <= 20) {
            $generalSubsets = $this->findSubsetsMatchingSum($pendingPayments->values(), $target, 4);
            foreach ($generalSubsets as $subset) {
                $orderIds = $subset->pluck('order_id')->unique()->implode(', #');
                $bundles->push([
                    'payments'   => $subset,
                    'confidence' => 75,
                    'reason'     => "Subconjunto de {$subset->count()} pedidos (#{$orderIds}) baixados no mesmo intervalo somando R$ " . number_format($target, 2, ',', '.'),
                ]);
            }
        }

        return $bundles->sortByDesc('confidence')->values();
    }

    /**
     * Algoritmo de Subset-Sum para coleções pequenas (N <= 5)
     */
    protected function findSubsetsMatchingSum(Collection $items, float $target, int $maxDepth = 5): array
    {
        $matches = [];
        $count = $items->count();

        // Tamanho 2
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $sum = (float)$items[$i]->amount + (float)$items[$j]->amount;
                if (abs($sum - $target) < 0.02) {
                    $matches[] = collect([$items[$i], $items[$j]]);
                }
            }
        }

        // Tamanho 3
        if ($maxDepth >= 3 && $count >= 3) {
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    for ($k = $j + 1; $k < $count; $k++) {
                        $sum = (float)$items[$i]->amount + (float)$items[$j]->amount + (float)$items[$k]->amount;
                        if (abs($sum - $target) < 0.02) {
                            $matches[] = collect([$items[$i], $items[$j], $items[$k]]);
                        }
                    }
                }
            }
        }

        // Tamanho 4
        if ($maxDepth >= 4 && $count >= 4) {
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    for ($k = $j + 1; $k < $count; $k++) {
                        for ($l = $k + 1; $l < $count; $l++) {
                            $sum = (float)$items[$i]->amount + (float)$items[$j]->amount + (float)$items[$k]->amount + (float)$items[$l]->amount;
                            if (abs($sum - $target) < 0.02) {
                                $matches[] = collect([$items[$i], $items[$j], $items[$k], $items[$l]]);
                            }
                        }
                    }
                }
            }
        }

        return $matches;
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
            // 1. Tenta 1:1
            $candidates = $this->findCandidatesFor($tx, $matchedPaymentIdsThisRun);
            if ($candidates->isNotEmpty()) {
                $top = $candidates->first();
                $second = $candidates->get(1);

                $isTied = $second && ($top['score'] - $second['score']) < 15;
                $tiedCandidates = $candidates->filter(fn ($c) => ($top['score'] - $c['score']) < 15);
                $isSameOrderTie = $tiedCandidates->pluck('order.id')->unique()->filter()->count() === 1;

                if ($top['score'] >= 45 && (! $isTied || $isSameOrderTie)) {
                    $this->linkSingle($tx, $top['order'], $top['payment']);
                    $matchedPaymentIdsThisRun[] = $top['payment']->id;
                    $promoted++;
                    continue;
                }
            }

            // 2. Tenta 1:N
            $bundles = $this->findBundleCandidatesFor($tx, $matchedPaymentIdsThisRun);
            if ($bundles->isNotEmpty()) {
                $best = $bundles->first();
                if ($best['confidence'] >= 70) {
                    $this->linkBundle($tx, $best['payments'], $best['reason']);
                    foreach ($best['payments'] as $bp) {
                        $matchedPaymentIdsThisRun[] = $bp->id;
                    }
                    $promoted++;
                }
            }
        }

        // 3. Tenta N:1 (Múltiplas passadas na maquininha somando 1 pagamento único no PDV)
        $promoted += $this->reconcileMultiSwipePayments($matchedPaymentIdsThisRun);

        return $promoted;
    }

    /**
     * Concilia N transações órfãs da maquininha contra 1 pagamento único no PDV
     * (Ex: cliente dividiu R$ 129,00 em 3 passadas de R$ 43,00 na máquina, mas PDV lançou R$ 129,00 único).
     */
    public function reconcileMultiSwipePayments(array &$excludePaymentIds = []): int
    {
        $promoted = 0;

        $unmatchedPayments = Payment::with(['order', 'paymentMethod'])
            ->where('is_paid', true)
            ->whereNull('acquirer_transaction_id')
            ->whereDoesntHave('acquirerTransactions')
            ->where(function ($q) {
                $q->whereHas('paymentMethod', function ($pm) {
                    $pm->whereIn('engine_type', [
                        \App\Enums\PaymentEngineType::POS_MANUAL->value,
                        \App\Enums\PaymentEngineType::SMART_POS->value,
                        \App\Enums\PaymentEngineType::TEF->value,
                        \App\Enums\PaymentEngineType::PIX_MACHINE->value,
                    ])->where('id', '!=', 13);
                })->orWhere(function ($fallback) {
                    $fallback->whereNull('payment_method_id')
                        ->where(function ($m) {
                            $m->where('method', 'like', '%Crédito%')
                              ->orWhere('method', 'like', '%Credito%')
                              ->orWhere('method', 'like', '%Débito%')
                              ->orWhere('method', 'like', '%Debito%')
                              ->orWhere('method', 'like', '%Pix Máq%')
                              ->orWhere('method', 'like', '%Pix Maq%');
                        });
                });
            })
            ->when(!empty($excludePaymentIds), fn($q) => $q->whereNotIn('id', $excludePaymentIds))
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($unmatchedPayments as $payment) {
            $target = round((float) $payment->amount, 2);
            if ($target <= 0 || !$payment->created_at) continue;

            $windowStart = $payment->created_at->copy()->subMinutes(45);
            $windowEnd   = $payment->created_at->copy()->addMinutes(45);

            $methodName = Str::ascii(mb_strtolower($payment->method ?? ''));
            $isCredit = str_contains($methodName, 'cred');
            $isDebit  = str_contains($methodName, 'deb');
            $isPix    = str_contains($methodName, 'pix');

            $orphanTxs = AcquirerTransaction::whereIn('status', [
                    AcquirerMatchStatus::ORPHAN,
                    AcquirerMatchStatus::AMBIGUOUS,
                ])
                ->whereBetween('transaction_date', [$windowStart, $windowEnd])
                ->where('gross_amount', '<', $target)
                ->orderBy('transaction_date', 'asc')
                ->get()
                ->filter(function ($tx) use ($isCredit, $isDebit, $isPix) {
                    $mod = Str::ascii(mb_strtolower($tx->modality ?? ''));
                    if ($isCredit) return str_contains($mod, 'cred');
                    if ($isDebit)  return str_contains($mod, 'deb');
                    if ($isPix)    return str_contains($mod, 'pix') || str_contains($mod, 'qrcode');
                    return true;
                })
                ->values();

            if ($orphanTxs->count() < 2 || $orphanTxs->count() > 12) continue;

            $subsets = $this->findAcquirerSubsetsMatchingSum($orphanTxs, $target, 4);
            if (count($subsets) === 1) {
                $matchedTxs = $subsets[0];
                $this->linkMultiSwipe($matchedTxs, $payment->order, $payment);
                $excludePaymentIds[] = $payment->id;
                $promoted += $matchedTxs->count();
            }
        }

        return $promoted;
    }

    protected function findAcquirerSubsetsMatchingSum(\Illuminate\Support\Collection $items, float $target, int $maxDepth = 4): array
    {
        $matches = [];
        $count = $items->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $sum = (float)$items[$i]->gross_amount + (float)$items[$j]->gross_amount;
                if (abs($sum - $target) < 0.02) {
                    $matches[] = collect([$items[$i], $items[$j]]);
                }
            }
        }

        if ($maxDepth >= 3 && $count >= 3) {
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    for ($k = $j + 1; $k < $count; $k++) {
                        $sum = (float)$items[$i]->gross_amount + (float)$items[$j]->gross_amount + (float)$items[$k]->gross_amount;
                        if (abs($sum - $target) < 0.02) {
                            $matches[] = collect([$items[$i], $items[$j], $items[$k]]);
                        }
                    }
                }
            }
        }

        if ($maxDepth >= 4 && $count >= 4) {
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    for ($k = $j + 1; $k < $count; $k++) {
                        for ($l = $k + 1; $l < $count; $l++) {
                            $sum = (float)$items[$i]->gross_amount + (float)$items[$j]->gross_amount + (float)$items[$k]->gross_amount + (float)$items[$l]->gross_amount;
                            if (abs($sum - $target) < 0.02) {
                                $matches[] = collect([$items[$i], $items[$j], $items[$k], $items[$l]]);
                            }
                        }
                    }
                }
            }
        }

        return $matches;
    }

    public function linkMultiSwipe(\Illuminate\Support\Collection $acquirerTxs, ?Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($acquirerTxs, $order, $payment) {
            $fallbackBankId = BankAccount::where('bank_name', 'like', '%sicredi%')->first()?->id
                ?? BankAccount::first()?->id;

            foreach ($acquirerTxs as $tx) {
                $deviceName = $tx->device?->name ?? 'Maquininha';
                $desc = "Repasse " . strtoupper($tx->acquirer_name) . " - " . strtoupper($tx->modality) . " (NSU {$tx->nsu} | {$deviceName} | Rateio Pedido #{$order?->id})";

                $receivable = AccountReceivable::create([
                    'order_id'        => $order?->id,
                    'customer_id'     => $order?->customer_id,
                    'bank_account_id' => $payment->paymentMethod?->destination_account_id ?? $fallbackBankId,
                    'description'     => $desc,
                    'gross_amount'    => $tx->gross_amount,
                    'fee_amount'      => $tx->fee_amount,
                    'net_amount'      => $tx->net_amount,
                    'due_date'        => $tx->expected_settlement_date,
                    'status'          => 'pending',
                ]);

                $tx->update([
                    'status'                => AcquirerMatchStatus::MATCHED,
                    'order_id'              => $order?->id,
                    'payment_id'            => $payment->id,
                    'account_receivable_id' => $receivable->id,
                    'notes'                 => "Conciliado em Rateio N:1 ({$acquirerTxs->count()} passadas) com Pagamento #{$payment->id}",
                ]);
            }

            $leadTx = $acquirerTxs->first();
            if ($leadTx && !$payment->acquirer_transaction_id) {
                $payment->update([
                    'acquirer_transaction_id' => $leadTx->id,
                ]);
            }
        });
    }

    public function linkManually(AcquirerTransaction $acquirerTx, Order $order, ?Payment $payment = null): void
    {
        if ($payment) {
            $this->linkSingle($acquirerTx, $order, $payment);
        }
    }

    /**
     * Desfaz a conciliação de uma transação da maquininha, devolvendo-a para Órfã e recalculando o turno.
     */
    public function unlink(AcquirerTransaction $acquirerTx): void
    {
        $shiftId = null;

        DB::transaction(function () use ($acquirerTx, &$shiftId) {
            $locked = AcquirerTransaction::where('id', $acquirerTx->id)->lockForUpdate()->first();
            if (!$locked) return;

            if ($locked->payment_id) {
                $pay = Payment::find($locked->payment_id);
                if ($pay) {
                    $shiftId = $pay->cash_shift_id;
                    if ((int) $pay->acquirer_transaction_id === (int) $locked->id) {
                        $nextLinked = AcquirerTransaction::where('payment_id', $pay->id)
                            ->where('id', '!=', $locked->id)
                            ->first();
                        $pay->update(['acquirer_transaction_id' => $nextLinked?->id]);
                    }
                }
            }

            if (!$shiftId && $locked->order_id) {
                $shiftId = Order::where('id', $locked->order_id)->value('cash_shift_id');
            }

            Payment::where('acquirer_transaction_id', $locked->id)
                ->update(['acquirer_transaction_id' => null]);

            if ($locked->account_receivable_id) {
                $rec = AccountReceivable::find($locked->account_receivable_id);
                if ($rec && $rec->status !== 'paid') {
                    $rec->delete();
                }
            }

            $locked->update([
                'status'                => AcquirerMatchStatus::ORPHAN,
                'reconciliation_source' => null,
                'reconciled_by_name'    => null,
                'reconciled_at'         => null,
                'order_id'              => null,
                'payment_id'            => null,
                'account_receivable_id' => null,
                'notes'                 => 'Conciliação desfeita por ' . (auth()->user()?->name ?? 'Operador') . ' em ' . now()->format('d/m/Y H:i'),
            ]);
        });

        if ($shiftId) {
            $shift = \App\Models\CashShift::find($shiftId);
            if ($shift && ($shift->status instanceof \BackedEnum ? $shift->status->value : $shift->status) !== 'open') {
                $auditService = app(\App\Services\RestaurantOps\ShiftAuditService::class);
                $cashierService = app(\App\Services\RestaurantOps\ShiftCashierService::class);
                $expectedCash = (float) $cashierService->calculateExpectedCash($shift);
                $diag = $auditService->diagnose($shift, (float) ($shift->closing_balance_actual ?? 0), $shift->terminal_settlements ?? []);
                $electronicDiff = round((float) ($diag['electronic_difference'] ?? 0), 2);
                $cashDiff = round((float) ($diag['cash_difference'] ?? 0), 2);

                $shift->updateQuietly([
                    'closing_balance_expected' => $expectedCash,
                    'cash_breakage_amount'     => $cashDiff,
                    'apos_breakage_amount'     => $electronicDiff,
                    'has_breakage'             => abs($cashDiff) >= 0.05 || abs($electronicDiff) >= 0.05,
                ]);
            }
        }
    }
}
