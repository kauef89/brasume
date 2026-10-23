<?php

namespace App\Services\Financial\Acquirers;

use App\Models\Device;
use App\Models\Payment;
use App\Models\AccountReceivable;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AcquirerReconciliationEngine
{
    public function reconcile(array $dtos): array
    {
        $results = [
            'matched' => 0,
            'divergent' => 0,
            'divergences' => [],
        ];

        // Cache de terminais lógicos conhecidos
        $devicesByTerminal = Device::whereNotNull('terminal_code')->get()->keyBy('terminal_code');

        foreach ($dtos as $dto) {
            /** @var AcquirerTransactionDTO $dto */

            // 1. O Matcher procura pagamentos no ERP com tolerância de ± 15 minutos e Valor Exato
            $matchedPayment = Payment::with('order')
                ->where('amount', $dto->grossAmount)
                ->whereBetween('created_at', [
                    $dto->transactionDate->copy()->subMinutes(15),
                    $dto->transactionDate->copy()->addMinutes(15)
                ])
                ->whereHas('paymentMethod', function ($q) {
                    $q->whereIn('engine_type', ['smart_pos', 'pos_pinpad', 'manual', 'tef', 'pix_machine']);
                })
                // Se ainda não foi conciliado nesta NSU
                ->whereDoesntHave('transaction', function ($q) use ($dto) {
                    $q->where('description', 'like', "%NSU {$dto->nsu}%");
                })
                ->first();

            // Se não encontrou match perfeito, marca como divergência para intervenção humana
            if (!$matchedPayment) {
                $results['divergent']++;
                $deviceContext = $devicesByTerminal->get($dto->terminalCode);
                $deviceStr = $deviceContext ? $deviceContext->name : "Terminal {$dto->terminalCode}";

                $results['divergences'][] = [
                    'date' => $dto->transactionDate->format('d/m/Y H:i'),
                    'amount' => $dto->grossAmount,
                    'terminal' => $deviceStr,
                    'modality' => strtoupper($dto->modality . ' ' . $dto->brand),
                    'nsu' => $dto->nsu,
                    'reason' => 'Nenhum pagamento correspondente encontrado no PDV (mesmo valor e horário aproximado).'
                ];
                continue;
            }

            // Se achou o Match, criamos a Conta a Receber (AccountReceivable) exata
            DB::transaction(function () use ($dto, $matchedPayment) {
                $desc = "Repasse " . strtoupper($dto->acquirerName) . " - " . strtoupper($dto->modality) . " (NSU {$dto->nsu})";

                AccountReceivable::create([
                    'order_id'     => $matchedPayment->order_id,
                    'customer_id'  => $matchedPayment->order?->customer_id,
                    'description'  => $desc,
                    'gross_amount' => $dto->grossAmount,
                    'fee_amount'   => $dto->feeAmount,
                    'net_amount'   => $dto->netAmount,
                    'due_date'     => $dto->expectedSettlementDate,
                    'status'       => 'pending',
                ]);

                // Como bónus: enriquecemos a descrição do Payment/Transaction caso exista
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
}
