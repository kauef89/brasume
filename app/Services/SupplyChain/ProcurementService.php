<?php

namespace App\Services\SupplyChain;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\ShoppingListItem;
use App\Models\ProductCostHistory;
use App\Models\StorageLocation;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcurementService
{
    /**
     * Varre todo o estoque e calcula reposições com agrupamento em embalagens fechadas.
     */
    public function generateAutomatedShoppingList(bool $ignoreWeekdays = false): int
    {
        $todayStr = strtolower(now()->format('D'));

        $suppliersOrderingToday = Supplier::where('is_active', true)
            ->where(function ($q) use ($todayStr, $ignoreWeekdays) {
                if (!$ignoreWeekdays) {
                    $q->whereJsonContains('order_days', $todayStr)
                      ->orWhereNull('order_days');
                }
            })
            ->pluck('id');

        $productsToReplenish = Product::where('track_inventory', true)
            ->whereNotIn('status', ['archived'])
            ->whereIn('type', ['resale', 'ingredient', 'packaging', 'supply', 'consumable'])
            ->where(function ($q) {
                $q->whereRaw('current_stock <= COALESCE(min_stock, 0)')
                  ->orWhere('current_stock', '<=', 0);
            })
            ->get();

        $generatedCount = 0;

        foreach ($productsToReplenish as $product) {
            $currentStock = (float) $product->current_stock;
            $minStock = (float) ($product->min_stock ?? 0);
            $targetStock = (float) ($product->max_stock ?? ($product->par_level ?? ($minStock * 2)));

            $baseDeficit = max(1.0, round($targetStock - $currentStock, 3));
            $isCritical = $currentStock <= 0;

            // 🚀 MATEMÁTICA DE CONVERSÃO: Arredonda para fardos/caixas fechadas
            $packaging = $product->resolvePurchasePackaging();
            $factor = $packaging['factor'];

            if ($factor > 1) {
                $packsNeeded = (int) ceil($baseDeficit / $factor);
                $finalUnitsQty = $packsNeeded * $factor;
            } else {
                $packsNeeded = 0;
                $finalUnitsQty = $baseDeficit;
            }

            $supplierId = $product->supplier_id
                ?? ProductCostHistory::where('product_id', $product->id)->latest('id')->value('supplier_id');

            $isSupplierDay = $supplierId && $suppliersOrderingToday->contains($supplierId);

            if ($ignoreWeekdays || $isSupplierDay || $isCritical || empty($supplierId)) {
                $item = ShoppingListItem::firstOrNew([
                    'product_id' => $product->id,
                    'status'     => 'pending'
                ]);

                $item->suggested_qty = $item->exists ? max((float) $item->suggested_qty, $finalUnitsQty) : $finalUnitsQty;
                $item->urgency_level = $isCritical ? 'critical' : ($item->urgency_level ?? 'normal');
                if (!$item->supplier_id && $supplierId) {
                    $item->supplier_id = $supplierId;
                }

                $unitCost = (float) ($product->base_cost ?? 0);
                if ($unitCost > 0) {
                    $item->estimated_cost = round($item->suggested_qty * $unitCost, 2);
                }

                $item->save();
                $generatedCount++;
            }
        }

        Log::info("🛒 [ProcurementService] Varredura concluída. {$generatedCount} itens calculados com fator de conversão.");

        return $generatedCount;
    }

    /**
     * Adiciona manualmente com suporte a embalagens e caixas
     */
    public function addManualItem(int $productId, float $qty, ?string $notes = null, ?int $supplierId = null, bool $isPackInput = false): ShoppingListItem
    {
        $product = Product::findOrFail($productId);
        $packaging = $product->resolvePurchasePackaging();
        $factor = $packaging['factor'];

        // Se o operador informou "2 fardos", converte para as unidades de consumo correspondentes
        $finalUnits = ($isPackInput && $factor > 1) ? round($qty * $factor, 3) : $qty;

        $item = ShoppingListItem::firstOrNew([
            'product_id' => $product->id,
            'status'     => 'pending'
        ]);

        $item->suggested_qty = $item->exists ? ((float) $item->suggested_qty + $finalUnits) : $finalUnits;
        $item->urgency_level = 'manual';
        if ($notes) {
            $item->notes = trim(($item->notes ? $item->notes . ' | ' : '') . $notes);
        }

        $resolvedSupplierId = $supplierId
            ?? $item->supplier_id
            ?? $product->supplier_id
            ?? ProductCostHistory::where('product_id', $product->id)->latest('id')->value('supplier_id');

        if ($resolvedSupplierId) {
            $item->supplier_id = $resolvedSupplierId;
        }

        $unitCost = (float) ($product->base_cost ?? 0);
        if ($unitCost > 0) {
            $item->estimated_cost = round($item->suggested_qty * $unitCost, 2);
        }

        $item->save();

        return $item;
    }

    /**
     * MOMENTO 1: Carrega os produtos de um StorageLocation para contagem física rápida
     */
    public function getStorageLocationAuditData(int $locationId): array
    {
        $location = StorageLocation::with(['storageRules.product'])->findOrFail($locationId);

        $resale = [];
        $ingredients = [];
        $supplies = [];
        $manufactured = [];

        foreach ($location->storageRules as $rule) {
            $product = $rule->product;
            if (!$product || $product->status === 'archived') continue;

            // 🛑 Pratos montados são excluídos do levantamento de estoque
            if ($product->type === 'dish') continue;

            $packaging = $product->resolvePurchasePackaging();

            $itemData = [
                'rule_id'            => $rule->id,
                'product_id'         => $product->id,
                'name'               => $product->name,
                'type'               => $product->type,
                'current_stock'      => (float) $product->current_stock,
                'min_stock'          => (float) ($product->min_stock ?? 0),
                'max_stock'          => (float) ($product->max_stock ?? 0),
                'base_uom'           => $packaging['base_uom'],
                'purchase_uom'       => $packaging['purchase_uom'],
                'conversion_factor'  => $packaging['factor'],
                'is_pack'            => $packaging['is_pack'],
                'is_low'             => (float) $product->current_stock <= (float) ($product->min_stock ?? 0),
            ];

            if ($product->type === 'resale') {
                $resale[] = $itemData;
            } elseif ($product->type === 'ingredient') {
                $ingredients[] = $itemData;
            } elseif (in_array($product->type, ['supply', 'packaging', 'consumable'])) {
                $supplies[] = $itemData;
            } elseif ($product->type === 'manufactured') {
                $manufactured[] = $itemData;
            }
        }

        return [
            'location'     => ['id' => $location->id, 'name' => $location->name],
            'resale'       => $resale,
            'ingredients'  => $ingredients,
            'supplies'     => $supplies,
            'manufactured' => $manufactured,
        ];
    }

    /**
     * MOMENTO 1 (Submissão): Roteia as faltas do local físico para Compra ou Produção
     */
    public function submitLocationAudit(int $locationId, array $items): array
    {
        $purchasesCreated = 0;
        $productionCreated = 0;

        DB::transaction(function () use ($items, &$purchasesCreated, &$productionCreated) {
            foreach ($items as $entry) {
                $productId = (int) $entry['product_id'];
                $qtyNeeded = (float) ($entry['quantity'] ?? 0);
                if ($qtyNeeded <= 0) continue;

                $product = Product::find($productId);
                if (!$product) continue;

                if ($product->type === 'manufactured') {
                    // 🚀 ROTEAMENTO: Preparos internos vão para a fila da esteira de produção
                    if (class_exists(ProductionOrder::class)) {
                        ProductionOrder::create([
                            'product_id'   => $product->id,
                            'quantity'     => $qtyNeeded,
                            'status'       => 'pending',
                            'priority'     => 'high',
                            'created_by'   => auth()->id(),
                            'notes'        => 'Gerado via conferência no local: ' . ($entry['location_name'] ?? 'Armazém'),
                        ]);
                        $productionCreated++;
                    }
                } else {
                    // 🚀 ROTEAMENTO: Revenda, insumos e embalagens vão para a Lista de Compras
                    $isPack = !empty($entry['is_pack']);
                    $this->addManualItem($productId, $qtyNeeded, 'Conferência física no local', null, $isPack);
                    $purchasesCreated++;
                }
            }
        });

        return [
            'purchases_count'  => $purchasesCreated,
            'production_count' => $productionCreated,
        ];
    }

    /**
     * MOMENTO 2: Constrói a Matriz de Cotações com agrupamento em Caixas/Fardos
     */
    public function getSmartBiddingMatrix(): array
    {
        $pendingItems = ShoppingListItem::with(['product.category', 'supplier'])
            ->where('status', 'pending')
            ->get();

        $matrix = [];
        $unassignedKey = 'avulso';

        foreach ($pendingItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            $packaging = $product->resolvePurchasePackaging();
            $factor = $packaging['factor'];
            $baseQty = (float) $item->suggested_qty;

            // Calcula exibição de fardos e unidades
            if ($factor > 1) {
                $packsCount = (int) ceil($baseQty / $factor);
                $packDisplay = "{$packsCount} {$packaging['purchase_uom']} ({$baseQty} {$packaging['base_uom']})";
            } else {
                $packsCount = $baseQty;
                $packDisplay = "{$baseQty} {$packaging['base_uom']}";
            }

            $suppliersFound = [];

            if ($item->supplier_id && $item->supplier) {
                $suppliersFound[$item->supplier_id] = [
                    'supplier'  => $item->supplier,
                    'unit_cost' => (float) ($item->estimated_cost ? ($item->estimated_cost / max(1, $baseQty)) : ($product->base_cost ?? 0))
                ];
            } elseif ($product->supplier_id) {
                $sup = Supplier::find($product->supplier_id);
                if ($sup) {
                    $suppliersFound[$sup->id] = [
                        'supplier'  => $sup,
                        'unit_cost' => (float) ($product->base_cost ?? 0)
                    ];
                }
            }

            $histories = ProductCostHistory::with('supplier')
                ->where('product_id', $product->id)
                ->whereNotNull('supplier_id')
                ->selectRaw('supplier_id, MAX(id) as latest_id, unit_cost')
                ->groupBy('supplier_id', 'unit_cost')
                ->get();

            foreach ($histories as $h) {
                if ($h->supplier && !isset($suppliersFound[$h->supplier_id])) {
                    $suppliersFound[$h->supplier_id] = [
                        'supplier'  => $h->supplier,
                        'unit_cost' => (float) $h->unit_cost
                    ];
                }
            }

            $formatLine = function ($unitCost) use ($item, $product, $packaging, $factor, $packsCount, $packDisplay, $baseQty) {
                $costVal = (float) $unitCost;
                $packCost = round($costVal * $factor, 2);
                $totalEstimated = round($costVal * $baseQty, 2);

                return [
                    'id'                => $item->id,
                    'shopping_list_id'  => $item->id,
                    'product_id'        => $product->id,
                    'product_name'      => $product->name,
                    'suggested_qty'     => $baseQty,
                    'suggested_packs'   => $packsCount,
                    'pack_display'      => $packDisplay,
                    'uom'               => $packaging['base_uom'],
                    'purchase_uom'      => $packaging['purchase_uom'],
                    'conversion_factor' => $factor,
                    'is_pack'           => $packaging['is_pack'],
                    'urgency'           => $item->urgency_level,
                    'urgency_level'     => $item->urgency_level,
                    'last_cost'         => $costVal,
                    'pack_cost'         => $packCost,
                    'estimated_total'   => $totalEstimated,
                    'notes'             => $item->notes,
                ];
            };

            if (empty($suppliersFound)) {
                if (!isset($matrix[$unassignedKey])) {
                    $matrix[$unassignedKey] = [
                        'supplier_name'   => 'Compras Avulsas (Feira / Mercado)',
                        'order_days'      => [],
                        'estimated_total' => 0.0,
                        'items'           => []
                    ];
                }

                $cost = (float) ($item->estimated_cost ? ($item->estimated_cost / max(1, $baseQty)) : ($product->base_cost ?? 0));
                $line = $formatLine($cost);
                $matrix[$unassignedKey]['items'][] = $line;
                $matrix[$unassignedKey]['estimated_total'] = round($matrix[$unassignedKey]['estimated_total'] + $line['estimated_total'], 2);
            } else {
                foreach ($suppliersFound as $supId => $sData) {
                    $supplier = $sData['supplier'];

                    if (!isset($matrix[$supId])) {
                        $matrix[$supId] = [
                            'supplier_name'   => $supplier->trade_name ?: ($supplier->company_name ?: $supplier->name),
                            'order_days'      => is_array($supplier->order_days) ? $supplier->order_days : json_decode($supplier->order_days ?? '[]', true),
                            'estimated_total' => 0.0,
                            'items'           => []
                        ];
                    }

                    $line = $formatLine($sData['unit_cost']);
                    $matrix[$supId]['items'][] = $line;
                    $matrix[$supId]['estimated_total'] = round($matrix[$supId]['estimated_total'] + $line['estimated_total'], 2);
                }
            }
        }

        return $matrix;
    }

    public function approvePurchase(int $shoppingListId, int $supplierId, float $approvedQty, float $agreedCost): void
    {
        $item = ShoppingListItem::findOrFail($shoppingListId);

        $item->update([
            'supplier_id'    => $supplierId,
            'approved_qty'   => $approvedQty,
            'estimated_cost' => round($approvedQty * $agreedCost, 2),
            'status'         => 'ordered'
        ]);
    }
}
