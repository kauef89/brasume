<?php

namespace App\Services\Accounting;

use App\Models\BankStatement;
use App\Models\Transaction;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
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

            if ($lockedStatement->status === 'reconciled') {
                throw new Exception('Operação abortada: Este extrato bancário já foi conciliado.');
            }

            // 1. Cria a Transação Real amarrada ao Banco
            $transaction = Transaction::create([
                "bank_account_id"  => $lockedStatement->bank_account_id,
                "type"             => (in_array(strtolower($lockedStatement->type), ['in', 'credit']) || (float)$lockedStatement->amount >= 0) ? 'in' : 'out',
                "amount"           => abs((float) $lockedStatement->amount),
                "description"      => $description ?? $lockedStatement->description,
                "transaction_date" => clone $lockedStatement->bank_date,
                "supplier_id"      => $supplierId,
            ]);

            $isConsoleOrAuto = app()->runningInConsole() || !auth()->check();
            $source = $isConsoleOrAuto ? 'auto' : 'manual';
            $operator = $isConsoleOrAuto ? 'Motor Automático' : (auth()->user()?->name ?? 'Operador');

            // 2. Amarra o Extrato à Entidade
            $lockedStatement->update([
                "status"                => "reconciled",
                "reconciliation_source" => $source,
                "reconciled_by_name"    => $operator,
                "reconciled_at"         => now(),
                "conciliable_id"        => $conciliable->id,
                "conciliable_type"      => get_class($conciliable),
                "transaction_id"        => $transaction->id,
            ]);

            // 3. Atualiza Conta a Pagar (Vincula transaction_id para o Observer não duplicar)
            if ($conciliable instanceof AccountPayable) {
                $conciliable->update([
                    "status"         => "paid",
                    "payment_date"   => $lockedStatement->bank_date,
                    "transaction_id" => $transaction->id,
                ]);
            }
            // 4. Atualiza Lote e Contas Filhas
            elseif ($conciliable instanceof PaymentBatch) {
                $conciliable->update([
                    "status"       => "paid",
                    "payment_date" => $lockedStatement->bank_date
                ]);
                AccountPayable::where("payment_batch_id", $conciliable->id)->update([
                    "status"         => "paid",
                    "payment_date"   => $lockedStatement->bank_date,
                    "transaction_id" => $transaction->id,
                ]);
            }
            // 5. 🚀 Atualiza Conta a Receber (Liquida recebíveis de cartões/clientes)
            elseif ($conciliable instanceof AccountReceivable) {
                $conciliable->update([
                    "status"         => "paid",
                    "payment_date"   => $lockedStatement->bank_date,
                    "transaction_id" => $transaction->id,
                ]);
            }
            // 6. 🚀 Transferência Entre Contas / Pagamento de Fatura (Gera a contrapartida na conta destino)
            elseif ($conciliable instanceof \App\Models\BankAccount) {
                $counterpartType = in_array(strtolower($lockedStatement->type), ['out', 'debit']) || (float)$lockedStatement->amount < 0 ? 'in' : 'out';
                $originAccountName = $lockedStatement->bankAccount?->name ?? 'Conta Bancária';
                
                \App\Models\Transaction::create([
                    "bank_account_id"  => $conciliable->id,
                    "type"             => $counterpartType,
                    "amount"           => abs((float) $lockedStatement->amount),
                    "description"      => "Transferência de {$originAccountName}: " . ($description ?? $lockedStatement->description),
                    "transaction_date" => clone $lockedStatement->bank_date,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Desfaz a conciliação de uma linha do extrato bancário, estornando vínculos e devolvendo para Pendente.
     */
    public static function undo(BankStatement $statement): void
    {
        DB::transaction(function () use ($statement) {
            $locked = BankStatement::where('id', $statement->id)->lockForUpdate()->first();
            if (!$locked) return;

            $conciliable = $locked->conciliable;

            if ($conciliable instanceof AccountPayable) {
                $conciliable->update([
                    'status'         => 'pending',
                    'payment_date'   => null,
                    'transaction_id' => null,
                ]);
            } elseif ($conciliable instanceof PaymentBatch) {
                $conciliable->update([
                    'status'       => 'pending',
                    'payment_date' => null,
                ]);
                AccountPayable::where('payment_batch_id', $conciliable->id)->update([
                    'status'         => 'pending',
                    'payment_date'   => null,
                    'transaction_id' => null,
                ]);
            } elseif ($conciliable instanceof AccountReceivable) {
                $conciliable->update([
                    'status'         => 'pending',
                    'payment_date'   => null,
                    'transaction_id' => null,
                ]);
            }

            if ($locked->transaction_id) {
                Transaction::where('id', $locked->transaction_id)->delete();
            }

            $locked->update([
                'status'                => 'pending',
                'reconciliation_source' => null,
                'reconciled_by_name'    => null,
                'reconciled_at'         => null,
                'conciliable_type'      => null,
                'conciliable_id'        => null,
                'transaction_id'        => null,
            ]);
        });
    }
}
