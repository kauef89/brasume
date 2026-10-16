<?php

namespace App\Services\Accounting;

use App\Models\BankStatement;
use App\Models\Transaction;
use App\Models\AccountPayable;
use App\Models\PaymentBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Exception;

class BankReconciliationService
{
    public static function finalize(
        BankStatement $statement,
        Model $conciliable,
        ?int $supplierId = null,
        ?string $description = null
    ): Transaction {
        return DB::transaction(function () use ($statement, $conciliable, $supplierId, $description) {

            // 🚀 TRAVA PESSIMISTA: Bloqueia a linha do extrato para edição simultânea
            $lockedStatement = BankStatement::where('id', $statement->id)->lockForUpdate()->first();

            // 🚀 PROTEÇÃO DE CONCORRÊNCIA: Se já foi conciliado por outro request no milissegundo anterior, aborta!
            if ($lockedStatement->status === 'reconciled') {
                throw new Exception('Operação abortada: Este extrato bancário já foi conciliado.');
            }

            // 1. Cria a Transação Real amarrada ao Banco
            $transaction = Transaction::create([
                "bank_account_id" => $lockedStatement->bank_account_id,
                "type" => $lockedStatement->type,
                "amount" => abs((float) $lockedStatement->amount),
                "description" => $description ?? $lockedStatement->description,
                "transaction_date" => clone $lockedStatement->bank_date,
                "supplier_id" => $supplierId,
            ]);

            // 2. Amarra o Extrato à Entidade
            $lockedStatement->update([
                "status" => "reconciled",
                "conciliable_id" => $conciliable->id,
                "conciliable_type" => get_class($conciliable),
                "transaction_id" => $transaction->id,
            ]);

            // 3. Atualiza Conta a Pagar
            if ($conciliable instanceof AccountPayable) {
                $conciliable->update([
                    "status" => "paid",
                    "payment_date" => $lockedStatement->bank_date
                ]);
            }
            // 4. Atualiza Lote e Contas Filhas
            elseif ($conciliable instanceof PaymentBatch) {
                $conciliable->update([
                    "status" => "paid",
                    "payment_date" => $lockedStatement->bank_date
                ]);
                AccountPayable::where("payment_batch_id", $conciliable->id)->update([
                    "status" => "paid",
                    "payment_date" => $lockedStatement->bank_date
                ]);
            }

            return $transaction;
        });
    }
}
