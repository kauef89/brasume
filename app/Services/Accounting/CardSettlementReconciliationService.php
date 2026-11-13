<?php

namespace App\Services\Accounting;

use App\Models\AccountReceivable;
use App\Models\AcquirerTransaction;
use App\Models\BankStatement;
use App\Models\PaymentBatch;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CardSettlementReconciliationService
{
    public function normalizeBrand(?string $raw): ?string
    {
        $text = Str::ascii(mb_strtoupper(trim((string) $raw)));
        if ($text === '') return null;

        if (str_contains($text, 'MASTER') || str_contains($text, 'MDS') || str_contains($text, 'MAESTRO')) return 'MASTER';
        if (str_contains($text, 'VISA') || str_contains($text, 'ELECTRON')) return 'VISA';
        if (str_contains($text, 'ELO')) return 'ELO';
        if (str_contains($text, 'AMEX') || str_contains($text, 'AMERICAN')) return 'AMEX';
        if (str_contains($text, 'HIPER')) return 'HIPER';

        return null;
    }

    public function parseBankCardSettlement(BankStatement $stmt): ?array
    {
        $raw = Str::ascii(mb_strtoupper(trim(($stmt->counterparty_name ?: '') . ' ' . ($stmt->description ?: ''))));

        $isCardMovement = (($stmt->movement_type?->value ?? $stmt->movement_type) === 'card_in')
            || str_contains($raw, 'REPASSE')
            || str_contains($raw, 'SICREDI')
            || str_contains($raw, 'CIELO')
            || str_contains($raw, 'STONE')
            || str_contains($raw, 'REDE');

        if (!$isCardMovement) return null;

        $isAnticipation = str_contains($raw, 'ANTEC');
        $modality = null;

        if (str_contains($raw, 'DEBITO') || str_contains($raw, 'DEB ')) {
            $modality = 'debito';
        } elseif (str_contains($raw, 'CREDITO') || str_contains($raw, 'CRED ') || $isAnticipation) {
            $modality = 'credito';
        }

        $brand = $this->normalizeBrand($raw);
        if (!$modality && !$brand) return null;

        return [
            'modality'        => $modality,
            'brand'           => $brand,
            'is_anticipation' => $isAnticipation,
        ];
    }

    /**
     * Busca transações candidatas de uma bandeira/modalidade para um lançamento de extrato.
     */
    public function findCandidateTransactions(BankStatement $stmt, bool $widerWindow = false): Collection
    {
        $parsed = $this->parseBankCardSettlement($stmt);
        if (!$parsed || empty($parsed['brand'])) {
            return collect();
        }

        $brand = $parsed['brand'];
        $modality = $parsed['modality'];
        $isAnticipation = $parsed['is_anticipation'];
        $bankDate = $stmt->bank_date->copy()->startOfDay();

        $query = AcquirerTransaction::with('accountReceivable');

        if ($isAnticipation) {
            // Antecipação: vendas de crédito dos últimos dias cujo vencimento original era FUTURO (> bank_date)
            $lookbackDays = $widerWindow ? 18 : 10;
            $minTxDate = $bankDate->copy()->subDays($lookbackDays)->toDateString() . ' 00:00:00';
            $maxTxDate = $bankDate->copy()->subDay()->toDateString() . ' 23:59:59';
            $query->whereBetween('transaction_date', [$minTxDate, $maxTxDate])
                ->whereDate('expected_settlement_date', '>', $bankDate->toDateString());
        } else {
            // Repasse normal (Débito D+1 útil ou Crédito vencendo na data, dentro de 45 dias da venda)
            $daysBack = $widerWindow ? 5 : 2;
            $minDue = $bankDate->copy()->subDays($daysBack)->toDateString();
            $maxDue = $bankDate->copy()->addDay()->toDateString();
            $minCapture = $bankDate->copy()->subDays(45)->toDateString() . ' 00:00:00';
            $maxCapture = $bankDate->copy()->toDateString() . ' 23:59:59';
            $query->whereBetween('expected_settlement_date', [$minDue, $maxDue])
                ->whereBetween('transaction_date', [$minCapture, $maxCapture]);
        }

        return $query->orderBy('transaction_date', 'asc')->get()->filter(function (AcquirerTransaction $tx) use ($brand, $modality) {
            if ($this->normalizeBrand($tx->brand) !== $brand) {
                return false;
            }

            $txMod = Str::ascii(mb_strtolower((string) $tx->modality));
            if ($modality === 'debito' && !str_contains($txMod, 'deb')) {
                return false;
            }
            if ($modality === 'credito' && !str_contains($txMod, 'cred')) {
                return false;
            }

            if ($tx->account_receivable_id) {
                $ar = $tx->accountReceivable;
                if ($ar && $ar->status !== 'pending' && $ar->deleted_at === null) {
                    return false;
                }
            }

            $statusVal = $tx->status instanceof \BackedEnum ? $tx->status->value : $tx->status;
            return $statusVal !== 'ignored';
        })->values();
    }

    public function reconcilePendingCardSettlementsSince(string $since): array
    {
        $statements = BankStatement::where('status', 'pending')
            ->where('amount', '>', 0)
            ->where('bank_date', '>=', $since)
            ->where(function ($q) {
                $q->where('movement_type', 'card_in')
                    ->orWhere('description', 'like', '%Repasse%')
                    ->orWhere('counterparty_name', 'like', '%Repasse%');
            })
            ->orderBy('bank_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $reconciledBatches = 0;
        $reconciledReceivables = 0;

        foreach ($statements as $stmt) {
            $parsed = $this->parseBankCardSettlement($stmt);
            if (!$parsed || empty($parsed['brand'])) {
                continue;
            }

            $targetAmount = round(abs((float) $stmt->amount), 2);
            $brand = $parsed['brand'];
            $modality = $parsed['modality'];
            $isAnticipation = $parsed['is_anticipation'];

            $candidateTxs = $this->findCandidateTransactions($stmt, false);
            if ($candidateTxs->isEmpty()) {
                continue;
            }

            if ($isAnticipation) {
                $matchedAnticGroup = null;
                $matchedDiff = 0.0;

                foreach ([6, 7, 8, 9, 10] as $days) {
                    $cutoff = $stmt->bank_date->copy()->subDays($days)->startOfDay();
                    $subGroup = $candidateTxs->filter(fn ($tx) => $tx->transaction_date && $tx->transaction_date->gte($cutoff));
                    if ($subGroup->isEmpty()) continue;

                    $rawNetSum = $this->sumTransactionsNet($subGroup);
                    $diff = round($rawNetSum - $targetAmount, 2);
                    $ratio = $rawNetSum > 0 ? ($diff / $rawNetSum) : 1;

                    // Taxa RAV típica Sicredi entre 0.4% e 3.5%
                    if ($diff >= 0 && $ratio >= 0.004 && $ratio <= 0.035) {
                        $matchedAnticGroup = $subGroup;
                        $matchedDiff = $diff;
                        break;
                    }
                }

                if ($matchedAnticGroup) {
                    $this->createSettlementBatch($stmt, $matchedAnticGroup, $targetAmount, $brand, $modality, true, $matchedDiff);
                    $reconciledBatches++;
                    $reconciledReceivables += $matchedAnticGroup->count();
                }
                continue;
            }

            // Repasse Normal: agrupa por expected_settlement_date (e sub-agrupa por horizonte curto <=7d vs D+30)
            $byDate = $candidateTxs->groupBy(fn ($tx) => $tx->expected_settlement_date->toDateString());
            $orderedDates = $byDate->keys()->sortBy(fn ($d) => $d === $stmt->bank_date->toDateString() ? 0 : 1);

            $matchedGroup = null;
            foreach ($orderedDates as $dueDateStr) {
                $group = $byDate->get($dueDateStr);
                if (abs($this->sumTransactionsNet($group) - $targetAmount) < 0.02) {
                    $matchedGroup = $group;
                    break;
                }

                // Tenta separar liquidação curta (D+1..D+7) de D+30 na mesma data de vencimento
                $shortHorizon = $group->filter(fn ($tx) => $tx->transaction_date && $tx->transaction_date->diffInDays($tx->expected_settlement_date) <= 7);
                if ($shortHorizon->isNotEmpty() && $shortHorizon->count() < $group->count()) {
                    if (abs($this->sumTransactionsNet($shortHorizon) - $targetAmount) < 0.02) {
                        $matchedGroup = $shortHorizon->values();
                        break;
                    }
                }

                $longHorizon = $group->filter(fn ($tx) => $tx->transaction_date && $tx->transaction_date->diffInDays($tx->expected_settlement_date) > 7);
                if ($longHorizon->isNotEmpty() && $longHorizon->count() < $group->count()) {
                    if (abs($this->sumTransactionsNet($longHorizon) - $targetAmount) < 0.02) {
                        $matchedGroup = $longHorizon->values();
                        break;
                    }
                }
            }

            if ($matchedGroup) {
                $this->createSettlementBatch($stmt, $matchedGroup, $targetAmount, $brand, $modality, false, 0.0);
                $reconciledBatches++;
                $reconciledReceivables += $matchedGroup->count();
            }
        }

        return [
            'batches'     => $reconciledBatches,
            'receivables' => $reconciledReceivables,
        ];
    }

    public function sumTransactionsNet(Collection $txs): float
    {
        return round((float) $txs->sum(function (AcquirerTransaction $tx) {
            $ar = $tx->accountReceivable;
            if ($ar && $ar->deleted_at === null) {
                return (float) ($ar->net_amount > 0 ? $ar->net_amount : ($ar->gross_amount - $ar->fee_amount));
            }
            return (float) ($tx->net_amount > 0 ? $tx->net_amount : ($tx->gross_amount - $tx->fee_amount));
        }), 2);
    }

    public function createSettlementBatch(
        BankStatement $stmt,
        Collection $group,
        float $targetAmount,
        string $brand,
        ?string $modality,
        bool $isAnticipation,
        float $ravDiff
    ): PaymentBatch {
        return DB::transaction(function () use ($stmt, $group, $targetAmount, $brand, $modality, $isAnticipation, $ravDiff) {
            $modLabel = $isAnticipation
                ? 'Antecipação'
                : ($modality === 'credito' ? 'Crédito' : ($modality === 'debito' ? 'Débito' : 'Cartão'));
            $count = $group->count();
            $batchDesc = "Repasse {$modLabel} {$brand} ({$count} " . ($count === 1 ? 'venda' : 'vendas') . ")";

            $batch = PaymentBatch::create([
                'description'  => $batchDesc,
                'total_amount' => $targetAmount,
                'payment_date' => $stmt->bank_date,
                'status'       => 'paid',
            ]);

            $txRecord = Transaction::create([
                'bank_account_id'  => $stmt->bank_account_id,
                'type'             => 'in',
                'amount'           => $targetAmount,
                'description'      => $batchDesc,
                'transaction_date' => clone $stmt->bank_date,
            ]);

            $rawTotal = $this->sumTransactionsNet($group);
            $ravRatio = ($isAnticipation && $ravDiff > 0 && $rawTotal > 0) ? ($ravDiff / $rawTotal) : 0;

            foreach ($group as $acqTx) {
                $ar = $acqTx->accountReceivable;

                if (!$ar || $ar->deleted_at !== null) {
                    $netBase = (float) ($acqTx->net_amount > 0 ? $acqTx->net_amount : ($acqTx->gross_amount - $acqTx->fee_amount));
                    $ar = AccountReceivable::create([
                        'order_id'        => $acqTx->order_id,
                        'customer_id'     => null,
                        'bank_account_id' => $stmt->bank_account_id,
                        'description'     => "Repasse SICREDI - " . strtoupper((string) $acqTx->modality) . " (NSU {$acqTx->nsu})",
                        'gross_amount'    => $acqTx->gross_amount,
                        'fee_amount'      => $acqTx->fee_amount,
                        'net_amount'      => $netBase,
                        'due_date'        => $acqTx->expected_settlement_date,
                        'status'          => 'pending',
                    ]);
                    $acqTx->update(['account_receivable_id' => $ar->id]);
                }

                $newFee = (float) $ar->fee_amount;
                $newNet = (float) ($ar->net_amount > 0 ? $ar->net_amount : ($ar->gross_amount - $ar->fee_amount));

                if ($isAnticipation && $ravRatio > 0) {
                    $addedRav = round($newNet * $ravRatio, 2);
                    $newFee = round($newFee + $addedRav, 2);
                    $newNet = round((float) $ar->gross_amount - $newFee, 2);
                }

                $ar->update([
                    'status'         => 'paid',
                    'fee_amount'     => $newFee,
                    'net_amount'     => $newNet,
                    'payment_date'   => $stmt->bank_date,
                    'transaction_id' => $txRecord->id,
                ]);
            }

            $stmt->update([
                'status'                => 'reconciled',
                'reconciliation_source' => 'auto',
                'reconciled_by_name'    => 'Motor Cartões',
                'reconciled_at'         => now(),
                'conciliable_type'      => PaymentBatch::class,
                'conciliable_id'        => $batch->id,
                'transaction_id'        => $txRecord->id,
            ]);

            return $batch;
        });
    }
}