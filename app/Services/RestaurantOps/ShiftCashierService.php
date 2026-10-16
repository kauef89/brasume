<?php

namespace App\Services\RestaurantOps;

use App\Models\CashShift;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\Printer;
use App\Events\ShiftClosed;
use App\Services\Printing\Documents\ShiftClosureReport;
use App\Services\Printing\PrintDispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

    public function closeShift(CashShift $shift, array $data, int $operatorId, string $operatorName): CashShift
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        DB::transaction(function () use ($shift, $data, $operatorId) {
            $updatePayload = [
                'status'                 => 'closed',
                'closed_at'              => now(),
                'closing_balance_actual' => (float) ($data['amount'] ?? 0),
                'closed_by'              => $operatorId,
                'notes'                  => $data['notes'] ?? ($data['observation'] ?? null),
            ];

            if (true) {
                $updatePayload['accepting_orders'] = false;
            }

            $shift->update($updatePayload);
        });

        // 🚀 TRANSMISSÃO EM TEMPO REAL: Notifica PosApp, TeamApp e CustomerApp
        try {
            broadcast(new ShiftClosed($shift));
        } catch (\Throwable $e) {
            Log::error("🚨 [ShiftCashierService] Falha ao transmitir evento ShiftClosed: " . $e->getMessage());
        }

        // 🖨️ IMPRESSÃO IMEDIATA DO RELATÓRIO DE FECHAMENTO (Antes de qualquer rotina Artisan externa)
        try {
            $cashierPrinter = Printer::where('is_cashier', true)
                ->where('is_active', true)
                ->first()
                ?? Printer::where('is_active', true)->first();

            if ($cashierPrinter) {
                $report = new ShiftClosureReport($shift);
                $this->printDispatcher->dispatch($cashierPrinter, $report);
                Log::info("🖨️ [ShiftCashierService] Relatório do Turno #{$shift->id} enviado para a impressora [{$cashierPrinter->name}].");
            } else {
                Log::notice("⚠️ [ShiftCashierService] Nenhuma impressora de caixa ativa encontrada para o Turno #{$shift->id}.");
            }
        } catch (\Throwable $e) {
            Log::warning("⚠️ [ShiftCashierService] Falha ao imprimir relatório de fechamento do Turno #{$shift->id}: " . $e->getMessage());
        }

        // Sincronização de consumo WMS
        try {
            $affected = app(\App\Services\RestaurantOps\WmsRestockService::class)->syncPendingConsumptionToShelf($shift->id);
            Log::info("📦 [WmsRestock] Consumo de prateleira consolidado no Turno #{$shift->id} ({$affected} regras atualizadas).");
        } catch (\Throwable $e) {
            Log::error("🚨 [WmsRestock] Falha ao sincronizar reposição no fechamento do Turno #{$shift->id}: " . $e->getMessage());
        }

        // 🧹 Limpeza de comandas abandonadas com proteção de contexto multi-tenant
        $tenantId = $tenant?->id ?? (function_exists('tenant') && tenant() ? tenant('id') : null);
        $options = ['--on-shift-close' => true];

        if ($tenantId) {
            $options['--tenant'] = $tenantId;
        }

        try {
            Artisan::call('erp:process-abandoned-carts', $options);
        } catch (\Throwable $e) {
            Log::error("🚨 [ShiftCashierService] Falha ao purgar comandas no fechamento do caixa: " . $e->getMessage());
        } finally {
            // 🛡️ BLINDAGEM DE TENANCY: Restaura o tenant caso o comando Artisan tenha resetado a conexão
            if ($tenant && (!function_exists('tenant') || !tenant())) {
                tenancy()->initialize($tenant);
            }
        }

        Log::info("🏁 [ShiftCashierService] Turno #{$shift->id} fechado pelo operador [{$operatorName}]. Ciclo completo.");

        return $shift;
    }

    public function closeShiftWithCleanup(CashShift $shift, array $data, int $operatorId, string $operatorName): CashShift
    {
        return $this->closeShift($shift, $data, $operatorId, $operatorName);
    }

    public function recordTransaction(array $data, string $operatorName): Transaction
    {
        $shift = CashShift::where('status', 'open')->first();

        if (!$shift) {
            throw new \Exception('Nenhum turno aberto para registrar a movimentação.');
        }

        $typeLabel = $data['type'] === 'out' ? 'Sangria' : 'Suprimento';

        $transaction = Transaction::create([
            'cash_shift_id'   => $shift->id,
            'bank_account_id' => $shift->bank_account_id,
            'type'            => $data['type'],
            'category'        => $data['type'] === 'out' ? 'cash_out' : 'cash_in',
            'amount'          => (float) $data['amount'],
            'description'     => $data['description'] ?? "{$typeLabel} manual",
            'operator_name'   => $operatorName,
            'transaction_date'=> now(),
        ]);

        Log::info("💸 [ShiftCashierService] Movimentação avulsa ({$data['type']}) de R$ {$data['amount']} registrada no Turno #{$shift->id}.");

        return $transaction;
    }

    public function rollbackPayment(Order $order, int|string $paymentId): Payment
    {
        return DB::transaction(function () use ($order, $paymentId) {
            /** @var Payment $payment */
            $payment = $order->payments()->with(['transaction', 'items'])->findOrFail($paymentId);

            try {
                if ($payment->relationLoaded('transaction') && $payment->transaction) {
                    $payment->transaction->delete();
                } else {
                    Transaction::where('order_id', $order->id)
                        ->where('payment_id', $payment->id)
                        ->delete();
                }
            } catch (\Throwable $e) {
                Transaction::where('order_id', $order->id)
                    ->where('payment_id', $payment->id)
                    ->delete();
            }

            if ($payment->items && $payment->items->isNotEmpty()) {
                foreach ($payment->items as $item) {
                    $pivotQty = (float) ($item->pivot->quantity ?? 1);
                    $pivotAmount = (float) ($item->pivot->amount ?? 0);

                    $item->paid_qty = max(0, round((float) ($item->paid_qty ?? 0) - $pivotQty, 4));
                    $item->save();

                    if ($item->parent_item_id) {
                        $parent = OrderItem::find($item->parent_item_id);
                        if ($parent && (float) ($parent->total_price ?? 0) > 0) {
                            $proportionalDecrease = ($pivotAmount / (float) $parent->total_price) * (float) ($parent->quantity ?? 1);
                            $parent->paid_qty = max(0, round((float) ($parent->paid_qty ?? 0) - $proportionalDecrease, 4));
                            $parent->save();
                        }
                    }

                    $subItems = OrderItem::where('parent_item_id', $item->id)->get();
                    if ($subItems->isNotEmpty() && (float) ($item->quantity ?? 1) > 0) {
                        $ratio = $pivotQty / (float) $item->quantity;
                        foreach ($subItems as $sub) {
                            $subDecrease = (float) ($sub->quantity ?? 1) * $ratio;
                            $sub->paid_qty = max(0, round((float) ($sub->paid_qty ?? 0) - $subDecrease, 4));
                            $sub->save();
                        }
                    }
                }
            } else {
                $remainingPaymentsCount = $order->payments()->where('id', '!=', $payment->id)->count();
                if ($remainingPaymentsCount === 0) {
                    $order->items()->update(['paid_qty' => 0]);
                }
            }

            if (method_exists($payment, 'items')) {
                $payment->items()->detach();
            }
            $payment->delete();

            $totalPaid = (float) $order->payments()->sum('amount');
            $totalOrder = (float) $order->total;

            if ($totalPaid <= 0.01) {
                $order->payment_status = 'pending';
                if (in_array($order->status, ['closed', 'completed'])) {
                    $order->status = 'pending';
                    if (true) {
                        $order->closed_at = null;
                    }
                }
            } elseif ($totalPaid < ($totalOrder - 0.01)) {
                $order->payment_status = 'partial';
                if (in_array($order->status, ['closed', 'completed'])) {
                    $order->status = 'pending';
                    if (true) {
                        $order->closed_at = null;
                    }
                }
            } else {
                $order->payment_status = 'paid';
            }

            $order->save();

            Log::info("↩️ [PaymentRollback] Pagamento #{$paymentId} do Pedido #{$order->id} estornado com integridade contábil e proporcional.");

            return $payment;
        });
    }
}
