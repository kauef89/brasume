<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->applyBalance($transaction, 1);
    }

    public function updated(Transaction $transaction): void
    {
        // Se mudou o valor, o tipo ou a conta de destino, revertemos o estado original e aplicamos o novo
        if ($transaction->isDirty(['amount', 'type', 'bank_account_id', 'cash_shift_id'])) {
            
            DB::transaction(function () use ($transaction) {
                // 1. Estorna o valor antigo das contas originais
                $this->updateTarget(
                    $transaction->getOriginal('bank_account_id'),
                    $transaction->getOriginal('cash_shift_id'),
                    $transaction->getOriginal('type'),
                    (float) $transaction->getOriginal('amount'),
                    -1 // Reverte
                );
                
                // 2. Aplica o valor novo nas contas atuais
                $this->applyBalance($transaction, 1);
            });
        }
    }

    public function deleted(Transaction $transaction): void
    {
        $this->applyBalance($transaction, -1);
    }

    public function restored(Transaction $transaction): void
    {
        $this->applyBalance($transaction, 1);
    }

    private function applyBalance(Transaction $transaction, int $multiplier): void
    {
        $this->updateTarget(
            $transaction->bank_account_id,
            $transaction->cash_shift_id,
            $transaction->type,
            (float) $transaction->amount,
            $multiplier
        );
    }

    private function updateTarget(?int $bankId, ?int $shiftId, string $type, float $amount, int $multiplier): void
    {
        if ($amount == 0) return;

        // Se for saída ('out'), o valor fica negativo. Se for entrada, fica positivo.
        $realValue = ($type === 'out' ? -$amount : $amount) * $multiplier;

        if ($bankId) {
            // O increment nativo do Laravel já lida perfeitamente com valores negativos (virando decrement)
            DB::table('bank_accounts')->where('id', $bankId)->increment('current_balance', $realValue);
        }

        if ($shiftId) {
            // Só atualiza o saldo esperado se o turno ainda estiver ABERTO
            $shift = DB::table('cash_shifts')->where('id', $shiftId)->first(['status', 'closing_balance_expected']);
            if ($shift && $shift->status === 'open') {
                DB::table('cash_shifts')->where('id', $shiftId)->update([
                    'closing_balance_expected' => DB::raw("COALESCE(closing_balance_expected, opening_balance, 0) + ({$realValue})")
                ]);
            }
        }
    }
}
