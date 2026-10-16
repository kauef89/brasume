<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\LegacyOrder;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MigrateLegacyLoyaltyPoints extends Command
{
    /**
     * O nome e assinatura do comando.
     * Use a flag --customer=ID para testar em apenas 1 cliente primeiro!
     */
    protected $signature = 'tenant:migrate-legacy-loyalty {--customer= : ID de um cliente específico para teste}';

    protected $description = 'Ponte de Migração: Converte o histórico de pedidos legados em saldo de Pontos de Fidelidade / Cashback para o sistema novo.';

    public function handle()
    {
        $this->info("Iniciando migração de benefícios legados...");

        // 1. Carrega as regras atuais do Tenant
        $settings = CompanySetting::first();

        if (!$settings || !$settings->loyalty_is_active) {
            $this->error("O programa de fidelidade está desativado neste tenant. Abortando.");
            return;
        }

        $rule = $settings->loyalty_accumulation_rule ?? 'cart_value';
        $multiplier = (float) ($settings->loyalty_points_multiplier ?? 1);
        $expiryDays = (int) ($settings->loyalty_points_expiry_days ?? 0);

        $this->line("Regra de Acúmulo: <comment>{$rule}</comment>");
        $this->line("Multiplicador: <comment>{$multiplier}x</comment>");

        // 2. Define a query de clientes (Todos ou apenas o testado)
        $customerId = $this->option('customer');
        $customersQuery = Customer::query();

        if ($customerId) {
            $customersQuery->where('id', $customerId);
        }

        $customers = $customersQuery->get();
        $bar = $this->output->createProgressBar(count($customers));
        $bar->start();

        $totalPointsIssued = 0;
        $customersUpdated = 0;

        foreach ($customers as $customer) {

            // 3. Busca os pedidos legados finalizados que pertencem a este cliente
            $legacyOrders = LegacyOrder::with('items')
                ->whereIn('status', ['processed', 'completed', 'authorized', 'closed'])
                ->where(function ($query) use ($customer) {
                    $query->where('customer_id', $customer->id)
                          ->orWhereHas('legacyCustomer', function ($q) use ($customer) {
                              $q->where('customer_id', $customer->id);
                          });
                })
                ->get();

            if ($legacyOrders->isEmpty()) {
                $bar->advance();
                continue;
            }

            $pointsToAdd = 0;

            // 4. Calcula os pontos baseando-se no histórico
            foreach ($legacyOrders as $order) {
                if ($rule === 'cart_value') {
                    // Considera o valor pago (total_value no legado)
                    $pointsToAdd += (int) floor((float) $order->total_value * $multiplier);
                } elseif ($rule === 'product_qty_all') {
                    // Considera a quantidade de itens comprados
                    $totalQty = $order->items->sum('quantity');
                    $pointsToAdd += (int) floor($totalQty * $multiplier);
                }
            }

            if ($pointsToAdd > 0) {
                // 5. Injeta o saldo no cliente sem sobrescrever pontos que ele já ganhou na V3
                $customer->loyalty_points += $pointsToAdd;

                if ($expiryDays > 0) {
                    $customer->loyalty_points_expires_at = Carbon::now()->addDays($expiryDays);
                }

                $customer->save();

                $totalPointsIssued += $pointsToAdd;
                $customersUpdated++;
            }

            $bar->advance();
        }

        $bar->finish();

        $this->newLine(2);
        $this->info("✅ Migração Concluída com Sucesso!");
        $this->line("Clientes Atualizados: <comment>{$customersUpdated}</comment>");
        $this->line("Total de Pontos Injetados: <comment>{$totalPointsIssued}</comment>");

        if ($settings->loyalty_reward_type === 'cashback') {
            $conversion = (float) ($settings->loyalty_cashback_conversion_rate ?? 5);
            $cashbackGerado = number_format(($totalPointsIssued / 100) * $conversion, 2, ',', '.');
            $this->line("Valor Equivalente em Cashback Injetado na Economia: <comment>R$ {$cashbackGerado}</comment>");
        }
    }
}
