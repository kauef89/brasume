<?php

namespace App\Services\Financial;

use App\Models\AccountPayable;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountPayableSettlementService
{
    /**
     * Quita integralmente ou abate parcialmente um título em aberto.
     *
     * @return array{settled_payable: AccountPayable, remaining_payable: ?AccountPayable, is_partial: bool, remaining_amount: float}
     */
    public function settle(
        AccountPayable $payable,
        float $paidAmount,
        ?int $bankAccountId = null,
        ?int $transactionId = null,
        ?string $paymentDate = null,
        ?string $customNote = null
    ): array {
        if ($payable->status === 'paid') {
            throw new \Exception("O título #{$payable->id} já se encontra quitado.");
        }

        $currentAmount = round((float) $payable->amount, 2);
        $paidAmount = round($paidAmount, 2);

        if ($paidAmount <= 0) {
            throw new \Exception('O valor do pagamento deve ser maior que zero.');
        }

        if ($paidAmount > ($currentAmount + 0.01)) {
            throw new \Exception(
                'O valor informado (R$ ' . number_format($paidAmount, 2, ',', '.') .
                ') ultrapassa o saldo pendente do título (R$ ' . number_format($currentAmount, 2, ',', '.') . ').'
            );
        }

        $payDate = $paymentDate ?: now()->toDateString();
        $dateFormatted = Carbon::parse($payDate)->format('d/m/Y');
        $isPartial = ($currentAmount - $paidAmount) >= 0.01;

        return DB::transaction(function () use ($payable, $currentAmount, $paidAmount, $bankAccountId, $transactionId, $payDate, $dateFormatted, $customNote, $isPartial) {
            if (!$isPartial) {
                // Quitação Integral do título original
                $obsLines = array_filter([
                    $payable->observations,
                    $customNote ?: "[{$dateFormatted}] Quitado integralmente (R$ " . number_format($paidAmount, 2, ',', '.') . ")."
                ]);

                $payable->update([
                    'status'          => 'paid',
                    'payment_date'    => $payDate,
                    'bank_account_id' => $bankAccountId ?? $payable->bank_account_id,
                    'transaction_id'  => $transactionId ?? $payable->transaction_id,
                    'observations'    => implode("\n", $obsLines),
                ]);

                return [
                    'settled_payable'   => $payable->fresh(),
                    'remaining_payable' => null,
                    'is_partial'        => false,
                    'remaining_amount'  => 0.0,
                ];
            }

            // Abatimento Parcial:
            // 1. Reduz o saldo do título original (mantendo status = pending e sem disparar observer de status)
            $remainingAmount = round($currentAmount - $paidAmount, 2);
            $obsLines = array_filter([
                $payable->observations,
                $customNote ?: "[{$dateFormatted}] Abatimento parcial de R$ " . number_format($paidAmount, 2, ',', '.') .
                    " — Saldo remanescente: R$ " . number_format($remainingAmount, 2, ',', '.') . "."
            ]);

            $payable->updateQuietly([
                'amount'       => $remainingAmount,
                'observations' => implode("\n", $obsLines),
            ]);

            // 2. Cria o título-filho quitado para conciliação contábil e extrato do colaborador (prefixo "Vale / Abatimento" evita dupla provisão no Observer)
            $prefix = $payable->employee_id ? 'Vale / Abatimento Parcial' : 'Abatimento Parcial';
            $childPayable = AccountPayable::create([
                'employee_id'            => $payable->employee_id,
                'supplier_id'            => $payable->supplier_id,
                'supplier_name'          => $payable->supplier_name,
                'recurring_expense_id'   => $payable->recurring_expense_id,
                'description'            => "{$prefix}: {$payable->description} (Ref. #{$payable->id})",
                'amount'                 => $paidAmount,
                'due_date'               => $payDate,
                'payment_date'           => $payDate,
                'status'                 => 'paid',
                'is_personal'            => (bool) $payable->is_personal,
                'accounting_category'    => $payable->accounting_category,
                'accounting_subcategory' => $payable->accounting_subcategory,
                'account_holder'         => $payable->account_holder,
                'paid_by'                => $payable->paid_by ?? 'company',
                'bank_account_id'        => $bankAccountId,
                'transaction_id'         => $transactionId,
                'observations'           => "Abatimento parcial referente ao título #{$payable->id} ({$payable->description}). Saldo restante no título original: R$ " . number_format($remainingAmount, 2, ',', '.') . ".",
            ]);

            return [
                'settled_payable'   => $childPayable,
                'remaining_payable' => $payable->fresh(),
                'is_partial'        => true,
                'remaining_amount'  => $remainingAmount,
            ];
        });
    }
}
