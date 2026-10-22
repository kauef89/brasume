<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Tenant;

class CacheTopCombinations extends Command
{
    protected $signature = 'catalog:cache-top-combinations {--tenant=} {--days=30}';
    protected $description = 'Top 10: Ranking geral de produtos expandido nas top 3 variações de ingredientes e atualização do BI preditivo.';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);
        $tenantId = $this->option('tenant');
        $tenants = $tenantId ? Tenant::where('id', $tenantId)->get() : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error("Nenhum tenant encontrado.");
            return Command::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("\n==================================================");
            $this->info("🚀 PROCESSANDO RANKING INTELIGENTE & BI: {$tenant->id}");
            $this->info("   Janela considerada: últimos {$days} dias");
            $this->info("==================================================");

            tenancy()->initialize($tenant);

            try {
                // =========================================================================
                // 0. DICIONÁRIO DE OPÇÕES E GRUPOS DO TIPO 'INGREDIENT'
                // =========================================================================
                $v3Options = DB::table('modifier_options')
                    ->join('modifier_groups', 'modifier_options.modifier_group_id', '=', 'modifier_groups.id')
                    ->whereNull('modifier_options.deleted_at')
                    ->whereNull('modifier_groups.deleted_at')
                    ->select(
                        'modifier_options.id',
                        'modifier_options.name',
                        'modifier_groups.type as group_type'
                    )
                    ->get();

                $v3OptionsById = [];
                $v3OptionsByName = [];
                foreach ($v3Options as $opt) {
                    $v3OptionsById[$opt->id] = $opt;
                    $cleanName = mb_strtolower(trim($opt->name));
                    if (!isset($v3OptionsByName[$cleanName])) {
                        $v3OptionsByName[$cleanName] = $opt;
                    }
                }

                $productsWithIngredients = DB::table('modifier_group_product')
                    ->join('modifier_groups', 'modifier_group_product.modifier_group_id', '=', 'modifier_groups.id')
                    ->whereNull('modifier_groups.deleted_at')
                    ->where('modifier_groups.type', 'ingredient')
                    ->pluck('modifier_group_product.product_id')
                    ->unique()
                    ->toArray();

                // =========================================================================
                // 1. CARGA ATUAL (SISTEMA V3)
                // =========================================================================
                $currentItems = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->join('products', 'order_items.product_id', '=', 'products.id')
                    ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                    ->whereNull('products.deleted_at')
                    ->whereIn('orders.status', ['completed', 'closed', 'delivered', 'paid', 'finished'])
                    ->where('orders.created_at', '>=', $cutoffDate)
                    ->select(
                        'order_items.id',
                        'products.id as product_id',
                        'products.name',
                        'categories.name as category_name',
                        'products.status',
                        'products.track_inventory',
                        'products.current_stock',
                        'order_items.unit_price'
                    )
                    ->get()
                    ->map(fn($i) => (array) $i);

                $currentIds = $currentItems->pluck('id')->filter()->toArray();

                // Dicionário Estrito: Só aceita modificadores oficiais do tipo 'ingredient'
                $currentModifiers = empty($currentIds) ? collect() : DB::table('order_item_modifiers')
                    ->whereIn('order_item_id', $currentIds)
                    ->get()
                    ->map(function ($m) use ($v3OptionsById, $v3OptionsByName) {
                        $name = trim($m->name ?? '');
                        if (str_starts_with($name, '[') || empty($name)) return null;

                        $opt = null;
                        if (!empty($m->modifier_option_id) && isset($v3OptionsById[$m->modifier_option_id])) {
                            $opt = $v3OptionsById[$m->modifier_option_id];
                        } else {
                            $cleanKey = mb_strtolower($name);
                            $opt = $v3OptionsByName[$cleanKey] ?? null;
                        }

                        if (!$opt || $opt->group_type !== 'ingredient') return null;

                        return [
                            'order_item_id' => $m->order_item_id,
                            'id' => $opt->id,
                            'name' => $opt->name,
                            'quantity' => (float) ($m->quantity ?? 1),
                            'price' => (float) ($m->price ?? 0),
                        ];
                    })
                    ->filter()
                    ->groupBy('order_item_id');

                $currentMapped = $currentItems->map(function ($item) use ($currentModifiers) {
                    $item['modifiers'] = $currentModifiers->get($item['id'], collect())->toArray();
                    return $item;
                });

                // =========================================================================
                // 2. CARGA LEGADA
                // =========================================================================
                $legacyMapped = collect();
                $schema = DB::getSchemaBuilder();

                if ($schema->hasTable('legacy_orders') && $schema->hasTable('legacy_order_items')) {
                    $legacyItems = DB::table('legacy_order_items')
                        ->join('legacy_orders', 'legacy_order_items.legacy_order_id', '=', 'legacy_orders.id')
                        ->join('legacy_products', 'legacy_order_items.legacy_product_id', '=', 'legacy_products.id')
                        ->join('products', 'legacy_products.current_product_id', '=', 'products.id')
                        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                        ->whereNull('products.deleted_at')
                        ->where('legacy_products.is_ignored', 0)
                        ->whereIn('legacy_orders.status', ['completed', 'closed', 'delivered', 'paid', 'finished', 'authorized', 'processed'])
                        ->where('legacy_orders.issue_date', '>=', $cutoffDate)
                        ->whereNotNull('legacy_products.current_product_id')
                        ->select(
                            'legacy_order_items.id',
                            'products.id as product_id',
                            'products.name',
                            'categories.name as category_name',
                            'products.status',
                            'products.track_inventory',
                            'products.current_stock',
                            'legacy_order_items.unit_price'
                        )
                        ->get()
                        ->map(fn($i) => (array) $i);

                    $legacyIds = $legacyItems->pluck('id')->filter()->toArray();

                    $legacyModifiers = empty($legacyIds) ? collect() : DB::table('legacy_order_item_modifiers')
                        ->join('legacy_modifiers', 'legacy_order_item_modifiers.legacy_modifier_id', '=', 'legacy_modifiers.id')
                        ->join('modifier_options', 'legacy_modifiers.modifier_option_id', '=', 'modifier_options.id')
                        ->join('modifier_groups', 'modifier_options.modifier_group_id', '=', 'modifier_groups.id')
                        ->whereIn('legacy_order_item_modifiers.legacy_order_item_id', $legacyIds)
                        ->where('legacy_modifiers.is_ignored', 0)
                        ->whereNotNull('legacy_modifiers.modifier_option_id')
                        ->select(
                            'legacy_order_item_modifiers.legacy_order_item_id as order_item_id',
                            'modifier_options.id as option_id',
                            'modifier_options.name as option_name',
                            'legacy_order_item_modifiers.quantity',
                            'legacy_order_item_modifiers.price',
                            'modifier_groups.type as group_type'
                        )
                        ->get()
                        ->map(function ($m) {
                            if ($m->group_type !== 'ingredient') return null;
                            return [
                                'order_item_id' => $m->order_item_id,
                                'id' => $m->option_id,
                                'name' => $m->option_name,
                                'quantity' => (float) ($m->quantity ?? 1),
                                'price' => (float) ($m->price ?? 0),
                            ];
                        })
                        ->filter()
                        ->groupBy('order_item_id');

                    $legacyMapped = $legacyItems->map(function ($item) use ($legacyModifiers) {
                        $item['modifiers'] = $legacyModifiers->get($item['id'], collect())->toArray();
                        return $item;
                    });
                }

                $allItems = $currentMapped->merge($legacyMapped);

                if ($allItems->isEmpty()) {
                    $this->warn("⚠️ Nenhum item válido encontrado para o tenant {$tenant->id}.");
                    tenancy()->end();
                    continue;
                }

                // =========================================================================
                // 3. ATUALIZAÇÃO DO BI PREDITIVO (HISTORICAL_WEIGHT)
                // =========================================================================
                $optionVolumes = [];
                foreach ($allItems as $item) {
                    if (!empty($item['modifiers'])) {
                        foreach ($item['modifiers'] as $mod) {
                            $optId = $mod['id'];
                            $optionVolumes[$optId] = ($optionVolumes[$optId] ?? 0) + (float) ($mod['quantity'] ?? 1);
                        }
                    }
                }

                if (!empty($optionVolumes)) {
                    // Reseta os pesos das opções de ingredientes para refletir a amostra recente
                    DB::table('modifier_options')
                        ->join('modifier_groups', 'modifier_options.modifier_group_id', '=', 'modifier_groups.id')
                        ->where('modifier_groups.type', 'ingredient')
                        ->update(['modifier_options.historical_weight' => 0]);

                    // Grava os novos volumes minerados
                    foreach ($optionVolumes as $optId => $volume) {
                        DB::table('modifier_options')
                            ->where('id', $optId)
                            ->update(['historical_weight' => $volume]);
                    }

                    $this->info("📈 BI Atualizado: " . count($optionVolumes) . " opções de ingredientes receberam novos pesos estatísticos.");
                }

                // =========================================================================
                // 4. O RANKING INTELIGENTE
                // =========================================================================
                $groupedByProduct = $allItems->groupBy('product_id');
                $sortedProducts = $groupedByProduct->sortByDesc(fn($items) => $items->count());

                $finalData = [];

                foreach ($sortedProducts as $productId => $productItems) {
                    if (count($finalData) >= 10) break;

                    $requiresIngredients = in_array($productId, $productsWithIngredients);

                    $validItems = $productItems->filter(function($item) use ($requiresIngredients) {
                        if ($requiresIngredients && empty($item['modifiers'])) {
                            return false;
                        }
                        return true;
                    });

                    if ($validItems->isEmpty()) {
                        continue;
                    }

                    $combinations = $validItems->groupBy(function ($item) {
                        if (empty($item['modifiers'])) {
                            return $item['product_id'] . '_no_mods';
                        }
                        $modSignature = collect($item['modifiers'])
                            ->map(fn($m) => $m['id'] . 'x' . ($m['quantity'] ?? 1))
                            ->sort()
                            ->implode('-');

                        return $item['product_id'] . '_mods_' . $modSignature;
                    });

                    $topCombinations = $combinations->sortByDesc(fn($comb) => $comb->count())->take(3);

                    foreach ($topCombinations as $signature => $items) {
                        if (count($finalData) >= 10) break;

                        $sampleItem = $items->first();

                        $finalData[] = [
                            'id' => $sampleItem['product_id'],
                            'variant_signature' => $signature,
                            'name' => $sampleItem['name'],
                            'category_name' => $sampleItem['category_name'] ?? 'Mais Pedidos',
                            'unit_price' => (float) $sampleItem['unit_price'],
                            'status' => $sampleItem['status'] ?? 'active',
                            'track_inventory' => (bool) ($sampleItem['track_inventory'] ?? false),
                            'current_stock' => (float) ($sampleItem['current_stock'] ?? 99),
                            'modifiers' => collect($sampleItem['modifiers'] ?? [])->map(fn($mod) => [
                                'id' => $mod['id'],
                                'name' => $mod['name'],
                                'quantity' => (float) ($mod['quantity'] ?? 1),
                                'price' => (float) ($mod['price'] ?? 0),
                            ])->toArray(),
                            'sales_count' => $items->count(),
                            'base_product_sales' => $productItems->count(),
                        ];
                    }
                }

                // SALVA NO CACHE DO TENANT
                Cache::store('file')->put('tenant_top_combinations', $finalData, now()->addHours(25));

                // 🚀 AVISA O FRONTEND QUE O CARDÁPIO E O BI MUDARAM
                broadcast(new \App\Events\CatalogUpdated());

                if (empty($finalData)) {
                    $this->warn("Nenhuma combinação formada.");
                } else {
                    $tableRows = [];
                    foreach ($finalData as $index => $item) {
                        $modsSummary = collect($item['modifiers'])->map(function ($m) {
                            return (int)$m['quantity'] . 'x ' . $m['name'];
                        })->implode(', ');

                        $tableRows[] = [
                            $index + 1,
                            $item['name'],
                            $modsSummary ?: 'Sem modificadores',
                            $item['sales_count'] . 'x',
                            $item['base_product_sales'] . 'x'
                        ];
                    }

                    $this->table(
                        ['#', 'Produto Base', 'Variação Extraída', 'Vendas da Variação', 'Volume Global do Produto'],
                        $tableRows
                    );

                    $this->info("✅ Cache e BI Preditivo atualizados! Aplicativos notificados.");
                }

            } catch (\Exception $e) {
                $this->error("Erro no processamento: " . $e->getMessage());
            }

            tenancy()->end();
        }

        return Command::SUCCESS;
    }
}
