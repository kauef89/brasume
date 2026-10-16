<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Order;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;

class RecalculateLoyaltyBalances extends Command
{
    protected $signature = 'tenant:recalculate-loyalty {--customer= : ID de um cliente específico para teste}';

    protected $description = 'Higieniza a base zerando saldos corrompidos e recalcula os pontos baseado no histórico exato de compras V3 + Legado.';

    public function handle()
    {
        $this->info("Iniciando auditoria e recálculo completo de saldos de fidelidade...");

        // 1. Configurações de marketing
        $settings = CompanySetting::first();
        $multiplier = (float) ($settings->loyalty_points_multiplier ?? 1);
        $convRate = (float) ($settings->loyalty_cashback_conversion_rate ?? 5);

        $this->line("Multiplicador Padrão: <comment>{$multiplier}x</comment>");
        $this->line("Taxa de Conversão: <comment>{$convRate}%</comment>");

        // 2. Filtro de clientes
        $customerId = $this->option('customer');
        $customersQuery = Customer::query();

        if ($customerId) {
            $customersQuery->where('id', $customerId);
        }

        $customers = $customersQuery->get();
        $bar = $this->output->createProgressBar(count($customers));
        $bar->start();

        $totalFixes = 0;
        $totalRemovedPoints = 0;
        $totalGrantedPoints = 0;

        foreach ($customers as $customer) {
            $oldBalance = (int) $customer->loyalty_points;

            // --- A) PONTOS GANHOS NA V3 ---
            $v3PointsEarned = (int) Order::where('customer_id', $customer->id)
                ->whereIn('status', ['closed', 'completed', 'delivered', 'finished'])
                ->sum('loyalty_points_earned');

            // --- B) CASHBACK RESGATADO NA V3 ---
            // 1. Registro direto no pedido
            $cashbackFromOrders = (float) Order::where('customer_id', $customer->id)
                ->whereIn('status', ['closed', 'completed', 'delivered', 'finished'])
                ->sum('cashback_used');

            // 2. Registro na tabela `payments` (possui payment_method_id e method)
            $cashbackFromPayments = (float) DB::table('payments')
                ->join('orders', 'payments.order_id', '=', 'orders.id')
                ->where('orders.customer_id', $customer->id)
                ->where(function ($q) {
                    $q->where('payments.method', 'like', '%Cashback%')
                      ->orWhereExists(function ($sub) {
                          $sub->select(DB::raw(1))
                              ->from('payment_methods')
                              ->whereColumn('payment_methods.id', 'payments.payment_method_id')
                              ->where('payment_methods.engine_type', 'internal_loyalty');
                      });
                })
                ->sum('payments.amount');

            // 3. Registro na tabela `order_payments` (possui apenas payment_method em string)
            $cashbackFromOrderPayments = (float) DB::table('order_payments')
                ->join('orders', 'order_payments.order_id', '=', 'orders.id')
                ->where('orders.customer_id', $customer->id)
                ->where('order_payments.payment_method', 'like', '%Cashback%')
                ->sum('order_payments.amount');

            $totalCashbackSpent = max($cashbackFromOrders, $cashbackFromPayments, $cashbackFromOrderPayments);
            $pointsSpentVal = $totalCashbackSpent > 0 ? (int) ceil(($totalCashbackSpent / $convRate) * 100) : 0;

            // --- C) PONTOS DO LEGADO (1 real = 1 ponto via total_value) ---
            $legacySpentTotal = (float) DB::table('legacy_orders')
                ->leftJoin('legacy_customers', 'legacy_orders.legacy_customer_id', '=', 'legacy_customers.id')
                ->whereIn('legacy_orders.status', ['processed', 'completed', 'authorized', 'closed'])
                ->where(function ($q) use ($customer) {
                    $q->where('legacy_orders.customer_id', $customer->id)
                      ->orWhere('legacy_customers.customer_id', $customer->id);
                })
                ->sum('legacy_orders.total_value');

            $legacyPointsEarned = (int) floor($legacySpentTotal * $multiplier);

            // --- D) SALDO FINAL AUDITADO ---
            $newBalance = max(0, ($v3PointsEarned + $legacyPointsEarned) - $pointsSpentVal);

            if ($oldBalance !== $newBalance) {
                $customer->update(['loyalty_points' => $newBalance]);
                $totalFixes++;

                if ($oldBalance > $newBalance) {
                    $totalRemovedPoints += ($oldBalance - $newBalance);
                } else {
                    $totalGrantedPoints += ($newBalance - $oldBalance);
                }
            }

            $bar->advance();
        }

        $bar->finish();

        $this->newLine(2);
        $this->info("✅ Higienização e Recálculo Concluídos!");
        $this->line("Clientes Corrigidos: <comment>{$totalFixes}</comment>");
        $this->line("Excesso de Pontos Inflados Removidos: <comment>{$totalRemovedPoints}</comment>");
        $this->line("Novos Pontos Concedidos: <comment>{$totalGrantedPoints}</comment>");
    }
}
