<?php

namespace App\Services\Financial\Acquirers;

use App\Enums\AcquirerMatchStatus;
use App\Enums\PaymentEngineType;
use App\Models\Device;
use App\Models\Payment;
use App\Models\Order;
use App\Models\CashShift;
use App\Models\PaymentMethod;
use App\Models\AccountReceivable;
use App\Models\AcquirerTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        foreach ($transactions as $dto) {
            /** @var AcquirerTransactionDTO $dto */

            $device = $devicesByCode->get($dto->terminalCode);

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

            $scored = $this->findCandidatesFor($acquirerTx);

            if ($scored->isEmpty()) {
                $acquirerTx->update(['status' => AcquirerMatchStatus::ORPHAN]);
                $results['orphan']++;
                continue;
            }

            $topCandidate = $scored->first();
            $secondCandidate = $scored->get(1);

            $isTied = $secondCandidate && ($topCandidate['score'] - $secondCandidate['score']) < 15;
            if ($scored->count() > 1 && $isTied) {
                $acquirerTx->update([
                    'status' => AcquirerMatchStatus::AMBIGUOUS,
                    'notes'  => "Encontrados {$scored->count()} pedidos com valor idêntico no mesmo horário. Requer confirmação manual.",
                ]);
                $results['ambiguous']++;
                continue;
            }

            $matchedPayment = $topCandidate['payment'];

            DB::transaction(function () use ($dto, $matchedPayment, $acquirerTx, $device) {
                $order = $matchedPayment->order;
                $deviceName = $device ? "{$device->name} ({$dto->terminalCode})" : "Terminal {$dto->terminalCode}";
                $desc = "Repasse " . strtoupper($dto->acquirerName) . " - " . strtoupper($dto->modality) . " (NSU {$dto->nsu} | {$deviceName})";

                $receivable = AccountReceivable::create([
                    'order_id'        => $order?->id,
                    'customer_id'     => $order?->customer_id,
                    'bank_account_id' => $matchedPayment->paymentMethod?->destination_account_id,
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
     * Localiza e pontua pedidos candidatos para uma transação da maquininha.
     */
    public function findCandidatesFor(AcquirerTransaction $acquirerTx): Collection
    {
        $windowStart = $acquirerTx->transaction_date->copy()->subMinutes(25);
        $windowEnd   = $acquirerTx->transaction_date->copy()->addMinutes(25);

        // Busca pagamentos de mesmo valor na janela de tempo
        $candidates = Payment::query()
            ->with(['order.deliveryAddress', 'order.customer', 'paymentMethod'])
            ->where('amount', $acquirerTx->gross_amount)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereDoesntHave('order.accountReceivables', function ($q) use ($acquirerTx) {
                $q->where('description', 'like', "%NSU {$acquirerTx->nsu}%");
            })
            ->get();

        // Se não encontrar na janela de 25 min, busca em todo o dia
        if ($candidates->isEmpty()) {
            $candidates = Payment::query()
                ->with(['order.deliveryAddress', 'order.customer', 'paymentMethod'])
                ->where('amount', $acquirerTx->gross_amount)
                ->whereDate('created_at', $acquirerTx->transaction_date->toDateString())
                ->whereDoesntHave('order.accountReceivables', function ($q) use ($acquirerTx) {
                    $q->where('description', 'like', "%NSU {$acquirerTx->nsu}%");
                })
                ->get();
        }

        $device = $acquirerTx->device;

        return $candidates->map(function ($payment) use ($acquirerTx, $device) {
            $score = 0;
            $order = $payment->order;
            $scope = $device?->assignment_scope;

            if ($scope === 'delivery' && $order?->type === 'delivery') {
                $score += 40;
            } elseif ($scope === 'counter' && in_array($order?->type, ['counter', 'takeaway'])) {
                $score += 40;
            } elseif ($scope === 'waiter' && $order?->type === 'table') {
                $score += 40;
            }

            if ($device?->default_user_id && $order?->driver_id === $device->default_user_id) {
                $score += 30;
            }

            $diffSeconds = abs($acquirerTx->transaction_date->diffInSeconds($payment->created_at));
            if ($diffSeconds <= 120) {
                $score += 30;
            } elseif ($diffSeconds <= 300) {
                $score += 20;
            } elseif ($diffSeconds <= 900) {
                $score += 10;
            }

            return [
                'payment' => $payment,
                'order'   => $order,
                'score'   => min($score, 100),
            ];
        })->sortByDesc('score')->values();
    }

    /**
     * Vincula manualmente uma transação a um pedido específico.
     */
    public function linkManually(AcquirerTransaction $acquirerTx, Order $order, ?Payment $payment = null): void
    {
        DB::transaction(function () use ($acquirerTx, $order, $payment) {
            $device = $acquirerTx->device;
            $deviceName = $device ? "{$device->name} ({$acquirerTx->terminal_code})" : "Terminal {$acquirerTx->terminal_code}";
            $desc = "Repasse " . strtoupper($acquirerTx->acquirer_name) . " - " . strtoupper($acquirerTx->modality) . " (NSU {$acquirerTx->nsu} | {$deviceName})";

            $destinationAccountId = $payment?->paymentMethod?->destination_account_id
                ?? BankAccount::first()?->id;

            $receivable = AccountReceivable::create([
                'order_id'        => $order->id,
                'customer_id'     => $order->customer_id,
                'bank_account_id' => $destinationAccountId,
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
        });
    }

    /**
     * Cria um pedido retroativo de balcão quando a venda ocorreu na maquininha sem registro no caixa.
     */
    public function createRetroactiveOrder(AcquirerTransaction $acquirerTx, ?string $observation = null): Order
    {
        return DB::transaction(function () use ($acquirerTx, $observation) {
            $shift = CashShift::where('status', 'open')->latest()->first()
                ?? CashShift::whereDate('opened_at', $acquirerTx->transaction_date->toDateString())->latest()->first()
                ?? CashShift::latest()->first();

            $method = PaymentMethod::where('engine_type', PaymentEngineType::SMART_POS)
                ->orWhere('engine_type', PaymentEngineType::POS_MANUAL)
                ->first();

            $order = Order::create([
                'type'           => 'counter',
                'status'         => 'closed',
                'payment_status' => 'paid',
                'customer_name'  => 'Venda Maquininha (Consumidor)',
                'subtotal'       => $acquirerTx->gross_amount,
                'total'          => $acquirerTx->gross_amount,
                'cash_shift_id'  => $shift?->id,
                'notes'          => $observation ?: "Lançamento avulso gerado pela auditoria da maquininha (NSU {$acquirerTx->nsu})",
                'created_at'     => $acquirerTx->transaction_date,
                'closed_at'      => $acquirerTx->transaction_date,
            ]);

            $payment = Payment::create([
                'order_id'          => $order->id,
                'cash_shift_id'     => $shift?->id,
                'payment_method_id' => $method?->id,
                'amount'            => $acquirerTx->gross_amount,
                'method'            => strtoupper("{$acquirerTx->modality} {$acquirerTx->brand}"),
                'is_paid'           => true,
                'status'            => 'paid',
                'created_at'        => $acquirerTx->transaction_date,
            ]);

            $this->linkManually($acquirerTx, $order, $payment);

            return $order;
        });
    }

    /**
     * Marca uma transação para ser ignorada na auditoria.
     */
    public function ignore(AcquirerTransaction $acquirerTx, ?string $reason = null): void
    {
        $acquirerTx->update([
            'status' => AcquirerMatchStatus::IGNORED,
            'notes'  => $reason ?: 'Transação ignorada pelo operador.',
        ]);
    }
}
