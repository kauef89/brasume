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
     * 🧠 DIAGNÓSTICO INTELIGENTE PRÉ-FECHAMENTO:
     * Compara o saldo informado com o esperado e identifica se há um erro evidente de meio de pagamento.
     */
    public function diagnoseDiscrepancy(CashShift $shift, float $actualCash, array $terminalTotals = []): array
    {
        return app(\App\Services\RestaurantOps\ShiftAuditService::class)->diagnose($shift, $actualCash, $terminalTotals);
    }

    /**
     * 🔧 APLICA A CORREÇÃO DE FORMA DE PAGAMENTO E REGISTRA A FALHA OPERACIONAL NO TURNO
     */
    public function applyDiscrepancyCorrection(CashShift $shift, int $paymentId, int $newMethodId, string $operatorName): array
    {
        return app(\App\Services\RestaurantOps\ShiftAuditService::class)->applyCorrection($shift, $paymentId, $newMethodId, $operatorName);
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

            $terminalSettlements = $data['terminal_settlements'] ?? ($data['terminal_totals'] ?? null);
            $totalAposDeclared = 0;
            if (is_array($terminalSettlements)) {
                foreach ($terminalSettlements as $t) {
                    $totalAposDeclared += (float) ($t['total'] ?? 0);
                }
            }

            $updatePayload = [
                'status'                   => 'closed',
                'closed_at'                => $closedAt,
                'closing_balance_expected' => $expected,
                'closing_balance_actual'   => $actual,
                'cash_breakage_amount'     => $diff,
                'apos_breakage_amount'     => round($totalAposDeclared, 2),
                'terminal_settlements'      => $terminalSettlements,
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
            Log::warning("⚠️ Falha ao imprimir relatório de fechamento: " . $e->getMessage());
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
