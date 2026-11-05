<?php

namespace App\Services\RestaurantOps;

use App\Models\CashShift;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\AcquirerTransaction;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\Printer;
use App\Models\DiningTable;
use App\Models\User;
use App\Events\ShiftClosed;
use App\Services\Printing\Documents\ShiftClosureReport;
use App\Services\Printing\PrintDispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ShiftCashierService
{
    public function __construct(
        protected PrintDispatcher $printDispatcher
    ) {}

    public function openShift(array $data, int $operatorId, string $operatorName): CashShift
    {
        if (CashShift::where('status', 'open')->exists()) {
            throw new \Exception('Já existe um turno aberto neste terminal.');
        }

        $bankAccountId = $data['bank_account_id'] ?? BankAccount::first()?->id ?? 1;

        return DB::transaction(function () use ($data, $operatorId, $operatorName, $bankAccountId) {
            $shift = CashShift::create([
                'bank_account_id' => $bankAccountId,
                'status'          => 'open',
                'opened_at'       => now(),
                'opening_balance' => (float) ($data['amount'] ?? 0),
                'opened_by'       => $operatorId,
            ]);

            if (($data['amount'] ?? 0) > 0) {
                Transaction::create([
                    'cash_shift_id'   => $shift->id,
                    'bank_account_id' => $bankAccountId,
                    'type'            => 'in',
                    'category'        => 'opening_balance',
                    'amount'          => (float) $data['amount'],
                    'description'     => 'Abertura de Caixa (Troco Inicial)',
                    'operator_name'   => $operatorName,
                    'transaction_date'=> now(),
                ]);
            }

            broadcast(new \App\Events\ShiftOpened($shift));
            Log::info("🚀 [ShiftCashierService] Turno #{$shift->id} aberto pelo operador [{$operatorName}].");

            return $shift;
        });
    }

    public function calculateExpectedCash(CashShift $shift): float
    {
        $opening = (float) ($shift->opening_balance ?? 0);
        $openedAt = $shift->opened_at;
        $closedAt = $shift->closed_at ?? now();

        $moneyMethodIds = PaymentMethod::where('engine_type', 'money')->pluck('id')->all();

        // 1. Vendas em dinheiro (exclui delivery não finalizado)
        $cashSales = (float) Payment::where('is_paid', true)
            ->where(function ($q) use ($shift, $openedAt, $closedAt) {
                $q->where('cash_shift_id', $shift->id)
                  ->orWhereBetween('created_at', [$openedAt, $closedAt]);
            })
            ->whereHas('order', function ($q) {
                $q->where('status', 'closed')
                  ->orWhere(function ($sub) {
                      $sub->whereIn('status', ['completed', 'preparing', 'dispatched'])
                          ->where('type', '!=', 'delivery');
                  });
            })
            ->where(function ($q) use ($moneyMethodIds) {
                if (!empty($moneyMethodIds)) {
                    $q->whereIn('payment_method_id', $moneyMethodIds);
                }
                $q->orWhere('method', 'LIKE', '%Dinheiro%')
                  ->orWhere('method', 'LIKE', '%cash%');
            })
            ->sum('amount');

        // 2. Transações de gaveta (Suprimentos e Sangrias manuais)
        $shiftTransactions = Transaction::where('cash_shift_id', $shift->id)
            ->whereNull('order_id')
            ->get();

        $supplies = (float) $shiftTransactions->filter(function ($t) {
            return $t->category === 'supply'
                || ($t->type === 'in' && !str_contains(mb_strtolower($t->description ?? ''), 'abertura'));
        })->sum('amount');

        $sangrias = (float) $shiftTransactions->filter(function ($t) {
            return $t->category === 'bleed'
                || ($t->type === 'out' && !str_contains(mb_strtolower($t->description ?? ''), 'retirada de fechamento'));
        })->sum('amount');

        // 3. Trocos devolvidos
        $changeReturned = (float) Order::where('cash_shift_id', $shift->id)->sum('change_returned');

        return round($opening + $cashSales + $supplies - $sangrias - $changeReturned, 2);
    }

    /**
     * 🧠 DIAGNÓSTICO INTELIGENTE PRÉ-FECHO:
     * Compara o saldo informado com o esperado e identifica se há um erro evidente de meio de pagamento.
     */
    public function diagnoseDiscrepancy(CashShift $shift, float $actualCash): array
    {
        $expectedCash = $this->calculateExpectedCash($shift);
        $diff = round($actualCash - $expectedCash, 2);

        if (abs($diff) <= 0.05) {
            return [
                'has_discrepancy' => false,
                'difference'      => 0.00,
                'expected'        => $expectedCash,
                'actual'          => $actualCash,
                'suggestion'      => null,
            ];
        }

        $targetAmount = abs($diff);
        $openedAt = $shift->opened_at;
        $now = now();

        $suggestion = null;

        // CENÁRIO A: Falta dinheiro em gaveta (Ex: R$ -67,50)
        // O operador registou como Dinheiro no ecrã, mas o cliente pagou por Maquineta / Cartão / Pix
        if ($diff < 0) {
            $candidatePayments = Payment::with(['order', 'paymentMethod'])
                ->where('cash_shift_id', $shift->id)
                ->where(function ($q) {
                    $q->where('method', 'LIKE', '%Dinheiro%')
                      ->orWhere('method', 'LIKE', '%cash%');
                })
                ->whereBetween('amount', [$targetAmount - 0.05, $targetAmount + 0.05])
                ->latest('id')
                ->get();

            foreach ($candidatePayments as $payment) {
                // Procura transação equivalente na adquirente (APOS)
                $matchingApos = AcquirerTransaction::whereBetween('transaction_date', [$openedAt, $now])
                    ->whereBetween('gross_amount', [$targetAmount - 0.05, $targetAmount + 0.05])
                    ->latest('id')
                    ->first();

                $suggestedMethod = null;
                $reason = "";

                if ($matchingApos) {
                    $modality = mb_strtolower((string) $matchingApos->modality);
                    $isDebit = str_contains($modality, 'deb');
                    $isCredit = str_contains($modality, 'cred');
                    $isPix = str_contains($modality, 'pix');

                    $targetEngine = $isPix ? 'pix_machine' : ($isDebit ? 'manual' : 'manual');
                    $suggestedMethod = PaymentMethod::where('is_active', true)
                        ->where(function ($q) use ($isDebit, $isCredit, $isPix) {
                            if ($isDebit) $q->where('name', 'LIKE', '%Débito%');
                            elseif ($isCredit) $q->where('name', 'LIKE', '%Crédito%');
                            elseif ($isPix) $q->where('name', 'LIKE', '%Pix%');
                        })
                        ->first() ?? PaymentMethod::find(5);

                    $reason = "Identificada transação de R$ " . number_format($matchingApos->gross_amount, 2, ',', '.') .
                              " no terminal ({$matchingApos->brand} {$matchingApos->modality}) sem correspondência em gaveta.";
                } else {
                    // Sugestão baseada em padrão de Débito/Crédito
                    $suggestedMethod = PaymentMethod::where('is_active', true)
                        ->where('name', 'LIKE', '%Débito%')
                        ->first() ?? PaymentMethod::find(5);

                    $reason = "O Pedido #{$payment->order_id} possui um pagamento de R$ " . number_format($payment->amount, 2, ',', '.') .
                              " registado como Dinheiro que coincide exatamente com o valor em falta.";
                }

                if ($suggestedMethod) {
                    $suggestion = [
                        'payment_id'            => $payment->id,
                        'order_id'              => $payment->order_id,
                        'current_method_name'   => $payment->method,
                        'suggested_method_id'   => $suggestedMethod->id,
                        'suggested_method_name' => $suggestedMethod->name,
                        'amount'                => (float) $payment->amount,
                        'reason'                => $reason,
                        'type'                  => 'cash_to_electronic'
                    ];
                    break;
                }
            }
        }

        // CENÁRIO B: Sobra dinheiro em gaveta (Ex: R$ +67,50)
        // O cliente pagou em notas, mas o operador clicou em Débito/Pix no POS
        elseif ($diff > 0) {
            $candidatePayments = Payment::with(['order', 'paymentMethod'])
                ->where('cash_shift_id', $shift->id)
                ->where(function ($q) {
                    $q->where('method', 'NOT LIKE', '%Dinheiro%')
                      ->where('method', 'NOT LIKE', '%cash%');
                })
                ->whereBetween('amount', [$targetAmount - 0.05, $targetAmount + 0.05])
                ->latest('id')
                ->get();

            $cashMethod = PaymentMethod::where('engine_type', 'money')->first() ?? PaymentMethod::find(1);

            foreach ($candidatePayments as $payment) {
                $suggestion = [
                    'payment_id'            => $payment->id,
                    'order_id'              => $payment->order_id,
                    'current_method_name'   => $payment->method,
                    'suggested_method_id'   => $cashMethod->id,
                    'suggested_method_name' => $cashMethod->name,
                    'amount'                => (float) $payment->amount,
                    'reason'                => "O Pedido #{$payment->order_id} foi registado como '{$payment->method}', mas há um excedente físico idêntico na gaveta.",
                    'type'                  => 'electronic_to_cash'
                ];
                break;
            }
        }

        return [
            'has_discrepancy' => true,
            'difference'      => $diff,
            'expected'        => $expectedCash,
            'actual'          => $actualCash,
            'suggestion'      => $suggestion,
        ];
    }

    /**
     * 🔧 APLICA A CORREÇÃO DE FORMA DE PAGAMENTO E REGISTA A FALHA OPERACIONAL NO TURNO
     */
    public function applyDiscrepancyCorrection(CashShift $shift, int $paymentId, int $newMethodId, string $operatorName): array
    {
        return DB::transaction(function () use ($shift, $paymentId, $newMethodId, $operatorName) {
            $payment = Payment::with('order')->findOrFail($paymentId);
            $newMethod = PaymentMethod::findOrFail($newMethodId);

            $oldMethodName = $payment->method;
            $amount = (float) $payment->amount;

            // 1. Atualiza o Pagamento
            $payment->update([
                'payment_method_id' => $newMethod->id,
                'method'            => $newMethod->name,
            ]);

            // 2. Atualiza a Transação Contábil
            $trans = Transaction::where('payment_id', $payment->id)->first();
            if ($trans) {
                $isCash = $newMethod->engine_type === 'money';
                $destAccount = $newMethod->destination_account_id ?? ($isCash ? 1 : null);

                $trans->update([
                    'payment_method_id' => $newMethod->id,
                    'bank_account_id'   => $destAccount,
                    'description'       => "Pedido #{$payment->order_id} ({$newMethod->name})",
                ]);
            }

            // 3. Regista o histórico de erro corrigido no turno (Auditoria de Eficácia)
            $details = $shift->corrected_errors_details ?? [];
            $details[] = [
                'timestamp'        => now()->toDateTimeString(),
                'payment_id'       => $payment->id,
                'order_id'         => $payment->order_id,
                'amount'           => $amount,
                'from_method'      => $oldMethodName,
                'to_method'        => $newMethod->name,
                'operator_at_fix'  => $operatorName,
            ];

            $shift->increment('corrected_errors_count', 1);
            $shift->update([
                'corrected_errors_amount'  => (float) ($shift->corrected_errors_amount + $amount),
                'corrected_errors_details' => $details,
            ]);

            Log::warning("⚠️ [Auditoria Turno #{$shift->id}] Erro de meio de pagamento corrigido: Pedido #{$payment->order_id} alterado de '{$oldMethodName}' para '{$newMethod->name}' (R$ {$amount}) por [{$operatorName}].");

            $newExpected = $this->calculateExpectedCash($shift);

            return [
                'success'      => true,
                'new_expected' => $newExpected,
                'message'      => "Pagamento corrigido com sucesso para {$newMethod->name}."
            ];
        });
    }

    public function closeShift(CashShift $shift, array $data, int $operatorId, string $operatorName): CashShift
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        DB::transaction(function () use ($shift, $data, $operatorId, $operatorName) {
            $actual = round((float) ($data['amount'] ?? 0), 2);
            $closedAt = now();

            $expected = $this->calculateExpectedCash($shift);
            $diff = round($actual - $expected, 2);

            $hasBreakage = abs($diff) >= 0.02;

            $updatePayload = [
                'status'                   => 'closed',
                'closed_at'                => $closedAt,
                'closing_balance_expected' => $expected,
                'closing_balance_actual'   => $actual,
                'cash_breakage_amount'     => $diff,
                'has_breakage'             => $hasBreakage,
                'closed_by'                => $operatorId,
                'notes'                    => $data['notes'] ?? ($data['observation'] ?? null),
                'accepting_orders'         => false,
            ];

            $shift->update($updatePayload);

            // Ajuste contábil de quebra de caixa no Cofre (#1) apenas se for diferença real não corrigida
            if ($hasBreakage) {
                $isShortage = $diff < 0;
                $amount = abs($diff);
                $label = $isShortage ? 'Falta' : 'Sobra';

                Transaction::create([
                    'bank_account_id'  => 1,
                    'cash_shift_id'    => null,
                    'type'             => $isShortage ? 'out' : 'in',
                    'category'         => $isShortage ? 'bleed' : 'supply',
                    'amount'           => $amount,
                    'description'      => "Quebra de Caixa ({$label}) - Turno #{$shift->id}",
                    'operator_name'    => $operatorName,
                    'transaction_date' => $closedAt,
                ]);

                Log::warning("🚨 [ShiftCashierService] Quebra definitiva registada no Turno #{$shift->id}: {$label} de R$ {$amount}.");
            }
        });

        try {
            broadcast(new ShiftClosed($shift));
        } catch (\Throwable $e) {}

        // Impressão térmica com indicação expressa de quebras e correções
        try {
            $cashierPrinter = Printer::where('is_cashier', true)->where('is_active', true)->first()
                ?? Printer::where('is_active', true)->first();

            if ($cashierPrinter) {
                $report = new ShiftClosureReport($shift);
                $this->printDispatcher->dispatch($cashierPrinter, $report);
            }
        } catch (\Throwable $e) {
            Log::warning("⚠️ Falha ao imprimir relatório de fecho: " . $e->getMessage());
        }

        try {
            app(\App\Services\RestaurantOps\WmsRestockService::class)->syncPendingConsumptionToShelf($shift->id);
        } catch (\Throwable $e) {}

        return $shift;
    }

    public function recordTransaction(array $data, string $operatorName): Transaction
    {
        $shift = CashShift::where('status', 'open')->first();
        if (!$shift) {
            throw new \Exception('Nenhum turno aberto.');
        }

        $typeLabel = $data['type'] === 'out' ? 'Sangria' : 'Suprimento';

        return Transaction::create([
            'cash_shift_id'   => $shift->id,
            'bank_account_id' => $shift->bank_account_id,
            'type'            => $data['type'],
            'category'        => $data['type'] === 'out' ? 'cash_out' : 'cash_in',
            'amount'          => (float) $data['amount'],
            'description'     => $data['description'] ?? "{$typeLabel} manual",
            'operator_name'   => $operatorName,
            'transaction_date'=> now(),
        ]);
    }
}
