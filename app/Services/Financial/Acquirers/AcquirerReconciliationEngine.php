<?php

namespace App\Services\Financial\Acquirers;

use App\Enums\AcquirerMatchStatus;
use App\Models\Device;
use App\Models\Payment;
use App\Models\Order;
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

        $matchedPaymentIdsThisRun = [];

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

            $scored = $this->findCandidatesFor($acquirerTx, $matchedPaymentIdsThisRun);

            if ($scored->isEmpty()) {
                $acquirerTx->update(['status' => AcquirerMatchStatus::ORPHAN]);
                $results['orphan']++;
                continue;
            }

            $topCandidate = $scored->first();
            $secondCandidate = $scored->get(1);

            $isTied = $secondCandidate && ($topCandidate['score'] - $secondCandidate['score']) < 15;

            // 🚀 DESEMPATE INTELIGENTE: Se empatou, mas todos os candidatos são do MESMO PEDIDO (racha de conta),
            // não é ambíguo! Associa ao primeiro pagamento disponível daquela comanda.
            $uniqueOrderIds = $scored->pluck('order.id')->unique()->filter();
            $isSameOrderTie = $uniqueOrderIds->count() === 1;

            if ($scored->count() > 1 && $isTied && ! $isSameOrderTie) {
                $acquirerTx->update([
                    'status' => AcquirerMatchStatus::AMBIGUOUS,
                    'notes'  => "Encontrados {$scored->count()} pedidos com valor idêntico no mesmo horário. Requer confirmação manual.",
                ]);
                $results['ambiguous']++;
                continue;
            }

            // Casa com o candidato eleito
            /** @var Payment $matchedPayment */
            $matchedPayment = $topCandidate['payment'];
            $order = $topCandidate['order'];
            $matchedPaymentIdsThisRun[] = $matchedPayment->id;

            DB::transaction(function () use ($acquirerTx, $matchedPayment, $order, $dto, $device) {
                $deviceName = $device?->name ?? 'Maquininha';
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
     * Encontra e pontua candidatos a pagamento para uma transação de maquininha.
     */
    public function findCandidatesFor(AcquirerTransaction $acquirerTx, array $excludePaymentIds = []): Collection
    {
        $windowStart = $acquirerTx->transaction_date->copy()->subMinutes(25);
        $windowEnd   = $acquirerTx->transaction_date->copy()->addMinutes(25);

        // 1. Busca na janela de tolerância de 25 minutos
        $query = Payment::query()
            ->with(['order.deliveryAddress', 'order.customer', 'paymentMethod'])
            ->where('amount', $acquirerTx->gross_amount)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereDoesntHave('acquirerTransaction')
            ->when(!empty($excludePaymentIds), fn ($q) => $q->whereNotIn('id', $excludePaymentIds));

        $candidates = $query->get();

        // 2. Fallback: se não achar na janela imediata, busca em todo o dia
        if ($candidates->isEmpty()) {
            $candidates = Payment::query()
                ->with(['order.deliveryAddress', 'order.customer', 'paymentMethod'])
                ->where('amount', $acquirerTx->gross_amount)
                ->whereDate('created_at', $acquirerTx->transaction_date->toDateString())
                ->whereDoesntHave('acquirerTransaction')
                ->when(!empty($excludePaymentIds), fn ($q) => $q->whereNotIn('id', $excludePaymentIds))
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
            } elseif ($diffSeconds <= 600) {
                $score += 20;
            } elseif ($diffSeconds <= 1800) {
                $score += 10;
            }

            return [
                'payment' => $payment,
                'order'   => $order,
                'score'   => $score,
            ];
        })->sortByDesc('score')->values();
    }

    public function linkManually(AcquirerTransaction $acquirerTx, Order $order, ?Payment $payment = null): void
    {
        DB::transaction(function () use ($acquirerTx, $order, $payment) {
            $device = $acquirerTx->device;
            $deviceName = $device?->name ?? 'Maquininha';
            $desc = "Repasse " . strtoupper($acquirerTx->acquirer_name) . " - " . strtoupper($acquirerTx->modality) . " (NSU {$acquirerTx->nsu} | {$deviceName})";

            $receivable = AccountReceivable::create([
                'order_id'        => $order->id,
                'customer_id'     => $order->customer_id,
                'bank_account_id' => $payment?->paymentMethod?->destination_account_id,
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

    public function createRetroactiveOrder(AcquirerTransaction $acquirerTx, ?string $observation = null): Order
    {
        return DB::transaction(function () use ($acquirerTx, $observation) {
            $notes = "Venda retroativa criada via conciliação de maquininha (NSU {$acquirerTx->nsu}).";
            if ($observation) {
                $notes .= " Obs: {$observation}";
            }

            $order = Order::create([
                'type'           => 'counter',
                'source'         => Order::SOURCE_CASHIER,
                'status'         => 'delivered',
                'payment_status' => 'paid',
                'total'          => $acquirerTx->gross_amount,
                'subtotal'       => $acquirerTx->gross_amount,
                'notes'          => $notes,
                'created_at'     => $acquirerTx->transaction_date,
                'updated_at'     => $acquirerTx->transaction_date,
            ]);

            $paymentMethod = PaymentMethod::where('engine_type', 'pos_card')->first()
                ?? PaymentMethod::where('name', 'like', '%cart%')->first();

            $payment = Payment::create([
                'order_id'          => $order->id,
                'payment_method_id' => $paymentMethod?->id,
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
}
