<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\ModifierOption;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessPredictiveWeights extends Command
{
    protected $signature = 'catalog:process-predictive-weights {--tenant= : ID do Tenant (ex: nativa)} {--days=60 : Janela histórica em dias}';
    protected $description = 'Minera o histórico real de vendas (V3 + Legado) e atualiza a propensão estatística (historical_weight) dos modificadores.';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $tenants = $tenantId ? Tenant::where('id', $tenantId)->get() : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error("Nenhum tenant encontrado.");
            return Command::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("\n==================================================");
            $this->info("📊 PROCESSANDO PESOS PREDITIVOS (BI): {$tenant->id}");
            $this->info("   Janela considerada: últimos {$days} dias");
            $this->info("==================================================");

            tenancy()->initialize($tenant);

            try {
                $optionCounts = [];

                // 1. Mineração V3 (order_item_modifiers)
                $v3Sales = DB::table('order_item_modifiers')
                    ->join('order_items', 'order_item_modifiers.order_item_id', '=', 'order_items.id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->whereIn('orders.status', ['completed', 'closed', 'delivered', 'paid', 'finished'])
                    ->where('orders.created_at', '>=', $cutoffDate)
                    ->select(
                        'order_item_modifiers.modifier_option_id',
                        'order_item_modifiers.name',
                        DB::raw('SUM(COALESCE(order_item_modifiers.quantity, 1)) as total_qty')
                    )
                    ->groupBy('order_item_modifiers.modifier_option_id', 'order_item_modifiers.name')
                    ->get();

                foreach ($v3Sales as $sale) {
                    $optId = $sale->modifier_option_id;
                    $qty = (float) $sale->total_qty;

                    if ($optId) {
                        $optionCounts[$optId] = ($optionCounts[$optId] ?? 0) + $qty;
                    } else {
                        $cleanName = mb_strtolower(trim($sale->name));
                        $found = ModifierOption::whereRaw('LOWER(TRIM(name)) = ?', [$cleanName])->first();
                        if ($found) {
                            $optionCounts[$found->id] = ($optionCounts[$found->id] ?? 0) + $qty;
                        }
                    }
                }

                // 2. Mineração Legada (Passando por legacy_order_items)
                $schema = DB::getSchemaBuilder();
                if ($schema->hasTable('legacy_orders') && $schema->hasTable('legacy_order_items') && $schema->hasTable('legacy_order_item_modifiers')) {
                    $legacySales = DB::table('legacy_order_item_modifiers')
                        ->join('legacy_modifiers', 'legacy_order_item_modifiers.legacy_modifier_id', '=', 'legacy_modifiers.id')
                        ->join('legacy_order_items', 'legacy_order_item_modifiers.legacy_order_item_id', '=', 'legacy_order_items.id')
                        ->join('legacy_orders', 'legacy_order_items.legacy_order_id', '=', 'legacy_orders.id')
                        ->where('legacy_modifiers.is_ignored', 0)
                        ->whereNotNull('legacy_modifiers.modifier_option_id')
                        ->whereIn('legacy_orders.status', ['completed', 'closed', 'delivered', 'paid', 'finished', 'authorized', 'processed'])
                        ->where('legacy_orders.issue_date', '>=', $cutoffDate)
                        ->select(
                            'legacy_modifiers.modifier_option_id',
                            DB::raw('SUM(COALESCE(legacy_order_item_modifiers.quantity, 1)) as total_qty')
                        )
                        ->groupBy('legacy_modifiers.modifier_option_id')
                        ->get();

                    foreach ($legacySales as $sale) {
                        $optId = $sale->modifier_option_id;
                        $qty = (float) $sale->total_qty;
                        $optionCounts[$optId] = ($optionCounts[$optId] ?? 0) + $qty;
                    }
                }

                if (empty($optionCounts)) {
                    $this->warn("Nenhum modificador vendido no período.");
                    tenancy()->end();
                    continue;
                }

                // 3. Atualização no Banco de Dados
                $updatedCount = 0;
                $tableRows = [];

                // Zera pesos de grupos do tipo ingredient para não manter lixo antigo
                DB::table('modifier_options')
                    ->join('modifier_groups', 'modifier_options.modifier_group_id', '=', 'modifier_groups.id')
                    ->where('modifier_groups.type', 'ingredient')
                    ->update(['modifier_options.historical_weight' => 0]);

                foreach ($optionCounts as $optionId => $totalVolume) {
                    $option = ModifierOption::find($optionId);
                    if (!$option) continue;

                    $option->update(['historical_weight' => $totalVolume]);
                    $updatedCount++;

                    $tableRows[] = [
                        $option->id,
                        $option->name,
                        $option->group?->name ?? 'Geral',
                        number_format($totalVolume, 0, ',', '.') . 'x'
                    ];
                }

                usort($tableRows, fn($a, $b) => (int)str_replace(['.', 'x'], '', $b[3]) <=> (int)str_replace(['.', 'x'], '', $a[3]));
                $this->table(['ID', 'Opção / Adicional', 'Grupo', 'Volume Escolhido'], array_slice($tableRows, 0, 15));

                $this->info("✅ {$updatedCount} modificadores tiveram seu peso estatístico atualizado.");

                broadcast(new \App\Events\CatalogUpdated());

            } catch (\Exception $e) {
                $this->error("Erro no processamento: " . $e->getMessage());
            }

            tenancy()->end();
        }

        return Command::SUCCESS;
    }
}
