<?php

namespace App\Services\Accounting;

use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\Order;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Transaction;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\PaymentBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Exception;

class BankReconciliationService
{
    /**
     * Liquida em par duas linhas opostas do mesmo extrato (Saída original + Devolução/Estorno),
     * mantendo ambas as transações no Livro Caixa para saldo diário exato e efeito líquido zero na DRE.
     */
    public static function pairRefundStatements(
        BankStatement $primary,
        BankStatement $counterpart,
        ?string $note = null
    ): void {
        DB::transaction(function () use ($primary, $counterpart, $note) {
            $firstId = min($primary->id, $counterpart->id);
            $secondId = max($primary->id, $counterpart->id);

            $lockedFirst = BankStatement::where('id', $firstId)->lockForUpdate()->first();
            $lockedSecond = BankStatement::where('id', $secondId)->lockForUpdate()->first();

            if (!$lockedFirst || !$lockedSecond) {
                throw new Exception('Lançamento de estorno não localizado.');
            }

            if ($lockedFirst->status === 'reconciled' || $lockedSecond->status === 'reconciled') {
                throw new Exception('Operação abortada: Um dos lançamentos do par já foi conciliado.');
            }

            $isConsoleOrAuto = app()->runningInConsole() || !auth()->check();
            $source = $isConsoleOrAuto ? 'auto' : 'manual';
            $operator = $isConsoleOrAuto ? 'Motor Automático' : (auth()->user()?->name ?? 'Operador');

            foreach ([[$lockedFirst, $lockedSecond], [$lockedSecond, $lockedFirst]] as [$current, $other]) {
                $isInflow = (float) $current->amount >= 0;
                $baseName = $current->counterparty_name ?: $current->description;
                $prefix = $isInflow ? 'Estorno Recebido: ' : 'Pagamento Estornado: ';
                $txDesc = $prefix . $baseName . ($note ? " ({$note})" : '');

                $tx = Transaction::create([
                    'bank_account_id'  => $current->bank_account_id,
                    'type'             => $isInflow ? 'in' : 'out',
                    'amount'           => abs((float) $current->amount),
                    'description'      => $txDesc,
                    'transaction_date' => clone $current->bank_date,
                ]);

                $current->update([
                    'status'                => 'reconciled',
                    'reconciliation_source' => $source,
                    'reconciled_by_name'    => $operator,
                    'reconciled_at'         => now(),
                    'conciliable_type'      => BankStatement::class,
                    'conciliable_id'        => $other->id,
                    'transaction_id'        => $tx->id,
                ]);
            }
        });
    }

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

            $isInflow = in_array(strtolower((string) $lockedStatement->type), ['in', 'credit'], true)
                || (float) $lockedStatement->amount >= 0;

            // 1. Cria a Transação Real amarrada ao Banco
            $transaction = Transaction::create([
                "bank_account_id"  => $lockedStatement->bank_account_id,
                "type"             => $isInflow ? 'in' : 'out',
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

            // 3. Atualiza Conta a Pagar
            if ($conciliable instanceof BankStatement) {
                if ($conciliable->transaction_id) {
                    Transaction::where('id', $conciliable->transaction_id)->delete();
                }
                $conciliable->update([
                    'status'                => 'pending',
                    'reconciliation_source' => null,
                    'reconciled_by_name'    => null,
                    'reconciled_at'         => null,
                    'conciliable_type'      => null,
                    'conciliable_id'        => null,
                    'transaction_id'        => null,
                ]);
            } elseif ($conciliable instanceof AccountPayable) {
                $conciliable->update([
                    "status"         => "paid",
                    "payment_date"   => $lockedStatement->bank_date,
                    "transaction_id" => $transaction->id,
                ]);
            }
            // 4. Atualiza Lote Bancário (Contas a Pagar + Lote CNAB 240 de Folha)
            elseif ($conciliable instanceof PaymentBatch) {
                $conciliable->update([
                    "status"       => "paid",
                    "payment_date" => $lockedStatement->bank_date,
                ]);

                AccountPayable::where("payment_batch_id", $conciliable->id)->update([
                    "status"         => "paid",
                    "payment_date"   => $lockedStatement->bank_date,
                    "transaction_id" => $transaction->id,
                ]);

                Payroll::where("payment_batch_id", $conciliable->id)->update([
                    "status"       => "paid",
                    "payment_date" => $lockedStatement->bank_date,
                ]);

                Payslip::where("payment_batch_id", $conciliable->id)->update([
                    "status"            => "paid",
                    "bank_statement_id" => $lockedStatement->id,
                ]);
            }
            // 5. Atualiza Folha de Pagamento Integral
            elseif ($conciliable instanceof Payroll) {
                $conciliable->update([
                    "status"       => "paid",
                    "payment_date" => $lockedStatement->bank_date,
                ]);

                Payslip::where("payroll_id", $conciliable->id)
                    ->where("status", "!=", "cancelled")
                    ->update([
                        "status"            => "paid",
                        "bank_statement_id" => $lockedStatement->id,
                    ]);
            }
            // 6. Atualiza Holerite Individual
            elseif ($conciliable instanceof Payslip) {
                $conciliable->update([
                    "status"            => "paid",
                    "bank_statement_id" => $lockedStatement->id,
                ]);

                if ($conciliable->payroll_id) {
                    $hasPendingPayslips = Payslip::where("payroll_id", $conciliable->payroll_id)
                        ->where("status", "pending")
                        ->exists();

                    if (!$hasPendingPayslips) {
                        Payroll::where("id", $conciliable->payroll_id)->update([
                            "status"       => "paid",
                            "payment_date" => $lockedStatement->bank_date,
                        ]);
                    }
                }
            }
            // 7. Atualiza Conta a Receber (Liquida recebíveis de cartões/clientes)
            elseif ($conciliable instanceof AccountReceivable) {
                $conciliable->update([
                    "status"         => "paid",
                    "payment_date"   => $lockedStatement->bank_date,
                    "transaction_id" => $transaction->id,
                ]);

                if ($conciliable->order_id) {
                    Order::where("id", $conciliable->order_id)->update([
                        "payment_status" => "paid",
                    ]);
                }
            }
            // 8. Transferência Entre Contas / Pagamento de Fatura (Gera contrapartida se for outra conta)
            elseif ($conciliable instanceof BankAccount) {
                if ((int) $conciliable->id !== (int) $lockedStatement->bank_account_id) {
                    $counterpartType = $isInflow ? 'out' : 'in';
                    $originAccountName = $lockedStatement->bankAccount?->name ?? 'Conta Bancária';

                    Transaction::create([
                        "bank_account_id"  => $conciliable->id,
                        "type"             => $counterpartType,
                        "amount"           => abs((float) $lockedStatement->amount),
                        "description"      => "Transferência de {$originAccountName}: " . ($description ?? $lockedStatement->description),
                        "transaction_date" => clone $lockedStatement->bank_date,
                    ]);
                }
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

            if ($conciliable instanceof BankStatement) {
                if ($conciliable->transaction_id) {
                    Transaction::where('id', $conciliable->transaction_id)->delete();
                }
                $conciliable->update([
                    'status'                => 'pending',
                    'reconciliation_source' => null,
                    'reconciled_by_name'    => null,
                    'reconciled_at'         => null,
                    'conciliable_type'      => null,
                    'conciliable_id'        => null,
                    'transaction_id'        => null,
                ]);
            } elseif ($conciliable instanceof AccountPayable) {
                $conciliable->update([
                    'status'         => 'pending',
                    'payment_date'   => null,
                    'transaction_id' => null,
                ]);
            } elseif ($conciliable instanceof PaymentBatch) {
                $hasPayrolls = Payroll::where('payment_batch_id', $conciliable->id)->exists();
                $conciliable->update([
                    'status'       => $hasPayrolls ? 'transmitted' : 'pending',
                    'payment_date' => null,
                ]);
                AccountPayable::where('payment_batch_id', $conciliable->id)->update([
                    'status'         => 'pending',
                    'payment_date'   => null,
                    'transaction_id' => null,
                ]);
                Payroll::where('payment_batch_id', $conciliable->id)->update([
                    'status' => 'approved',
                ]);
                Payslip::where('payment_batch_id', $conciliable->id)->update([
                    'status'            => 'pending',
                    'bank_statement_id' => null,
                ]);
                if ($locked->transaction_id) {
                    AccountReceivable::where('transaction_id', $locked->transaction_id)->update([
                        'status'         => 'pending',
                        'payment_date'   => null,
                        'transaction_id' => null,
                    ]);
                }
            } elseif ($conciliable instanceof Payroll) {
                $conciliable->update([
                    'status' => 'approved',
                ]);
                Payslip::where('payroll_id', $conciliable->id)->update([
                    'status'            => 'pending',
                    'bank_statement_id' => null,
                ]);
            } elseif ($conciliable instanceof Payslip) {
                $conciliable->update([
                    'status'            => 'pending',
                    'bank_statement_id' => null,
                ]);
                if ($conciliable->payroll_id) {
                    Payroll::where('id', $conciliable->payroll_id)->update([
                        'status' => 'approved',
                    ]);
                }
            } elseif ($conciliable instanceof AccountReceivable) {
                $conciliable->update([
                    'status'         => 'pending',
                    'payment_date'   => null,
                    'transaction_id' => null,
                ]);
            } elseif ($conciliable instanceof BankAccount && (int) $conciliable->id !== (int) $locked->bank_account_id) {
                $counterpartType = ((float) $locked->amount >= 0) ? 'out' : 'in';
                $counterpartTx = Transaction::where('bank_account_id', $conciliable->id)
                    ->where('type', $counterpartType)
                    ->where('amount', abs((float) $locked->amount))
                    ->whereDate('transaction_date', $locked->bank_date?->toDateString())
                    ->latest('id')
                    ->first();

                if ($counterpartTx) {
                    $counterpartTx->delete();
                }
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