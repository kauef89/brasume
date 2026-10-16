<?php

namespace App\Observers;

use App\Models\AccountPayable;
use App\Models\Transaction;

class AccountPayableObserver
{
    public function created(AccountPayable $payable): void
    {
        // 1. Lógica do Colaborador (Migrada do Model)
        if ($payable->employee_id) {
            $desc = strtolower($payable->description);
            $isVale = str_contains($desc, "vale") || str_contains($desc, "adiantamento");

            if (!$isVale) {
                $payable->launchLedger("credit", "Provisão de Serviço: " . $payable->description);
            }

            if ($payable->status === "paid") {
                $payable->launchLedger("debit", "Pagamento: " . $payable->description);
            }
        }

        // 2. Lógica Bancária: Se já nasceu PAGO, gera transação de saída
        if ($payable->status === 'paid' && $payable->bank_account_id) {
            $this->createBankTransaction($payable);
        }
    }

    public function updated(AccountPayable $payable): void
    {
        if ($payable->isDirty('status')) {
            
            // 1. Lógica do Colaborador
            if ($payable->employee_id) {
                if ($payable->status === "paid") {
                    $payable->launchLedger("debit", "Pagamento: " . $payable->description);
                }
                if ($payable->status === "pending" && $payable->getOriginal("status") === "paid") {
                    $payable->launchLedger("credit", "Estorno de Pagamento: " . $payable->description);
                }
            }

            // 2. Lógica Bancária: Efetuou pagamento
            if ($payable->status === 'paid' && $payable->bank_account_id) {
                $this->createBankTransaction($payable);
            }
            
            // 3. Lógica Bancária: Estornou (voltou pra pendente ou cancelado)
            if (in_array($payable->status, ['pending', 'cancelled']) && $payable->getOriginal('status') === 'paid') {
                Transaction::where('description', 'LIKE', "Baixa de Conta a Pagar #{$payable->id}%")->delete();
            }
        }
    }

    public function deleting(AccountPayable $payable): void
    {
        // 1. Lógica do Colaborador
        if ($payable->employee_id && $payable->status === 'pending') {
            $payable->launchLedger("debit", "Cancelamento Administrativo de Serviço: " . $payable->description);
        }

        // 2. Remove as saídas financeiras atreladas
        Transaction::where('description', 'LIKE', "Baixa de Conta a Pagar #{$payable->id}%")->delete();
    }

    /**
     * Auxiliar para criar a transação bancária
     */
    private function createBankTransaction(AccountPayable $payable): void
    {
        $beneficiary = $payable->employee_id 
            ? ($payable->employee?->name ?? 'Colaborador') 
            : ($payable->supplier?->trade_name ?? $payable->supplier?->name ?? 'Fornecedor');

        Transaction::create([
            'bank_account_id'  => $payable->bank_account_id,
            'type'             => 'out',
            'category'         => 'payable_settlement', // Ou 'supplier_payment' se preferir
            'amount'           => $payable->amount,
            'description'      => "Baixa de Conta a Pagar #{$payable->id} - {$beneficiary} ({$payable->description})",
            'operator_name'    => auth()->user()?->name ?? 'Sistema',
            'transaction_date' => $payable->payment_date ?? now(),
        ]);
    }
}
