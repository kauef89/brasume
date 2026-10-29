<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\TechnicalSheet;
use App\Models\InventoryLoss;
use App\Models\OrderItem;
use App\Models\ModifierOption;
use App\Models\StorageLocation;
use App\Events\StockLowDetected;
use App\Events\CatalogUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    private function deductFromStorageLocations(Product $product, float $qty): void
    {
        // Contorna a ausência da função FIELD() no SQLite verificando o driver
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        $locations = $product->storageLocations()
            ->when(!$isSqlite, function ($query) {
                // Ordenação original no MySQL
                return $query->orderByRaw("FIELD(type, 'display', 'intermediate', 'storage')");
            })
            ->when($isSqlite, function ($query) {
                // Ordenação simplificada para SQLite (baseada no sort_order, por exemplo)
                return $query->orderBy('sort_order');
            })
            ->get();

        foreach ($locations as $location) {
            if ($qty <= 0) break;

            $stock = (float) $location->pivot->current_stock;
            if ($stock > 0) {
                $take = min($stock, $qty);
                $product->storageLocations()->updateExistingPivot($location->id, [
                    'current_stock' => $stock - $take
                ]);
                $qty -= $take;
            }
        }
    }

    private function addToStorageLocations(Product $product, float $qtyToAdd): ?int
    {
        // 🚀 OTIMIZAÇÃO: Evita a query de verificação caso não tenha localizações
        if (!$product->relationLoaded('storageLocations') && DB::table('product_storage_location')->where('product_id', $product->id)->count() === 0) {
            return null;
        }

        $locations = $product->storageLocations()
            ->orderByRaw("FIELD(type, 'storage', 'intermediate', 'display')")
            ->get();

        if ($locations->isEmpty()) return null;

        $targetLoc = $locations->first();
        $currentLocal = (float) $targetLoc->pivot->current_stock;
        $product->storageLocations()->updateExistingPivot($targetLoc->id, [
            'current_stock' => $currentLocal + $qtyToAdd
        ]);

        return (int) $targetLoc->id;
    }

    /**
     * Registra o movimento imutável no Livro-Razão Kardex
     */
    public function logMovement(
        Product $product,
        string $type,
        float $quantity,
        float $newBalance,
        string $description,
        $originModel = null,
        ?int $locationId = null
    ): void {
        DB::table('inventory_movements')->insert([
            'product_id'          => $product->id,
            'storage_location_id' => $locationId,
            'type'                => $type,
            'quantity'            => $quantity,
            'balance_after'       => $newBalance,
            'unit_cost'           => (float) ($product->base_cost ?? 0),
            'origin_type'         => $originModel ? get_class($originModel) : null,
            'origin_id'           => $originModel ? $originModel->id : null,
            'description'         => $description,
            'user_id'             => auth()->id(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function addStock(Product $product, float $quantity, string $origin = 'compra', $originModel = null, ?int $locationId = null): void
    {
        if ($quantity <= 0) return;

        DB::transaction(function () use ($product, $quantity, $origin, $originModel, $locationId) {
            $wasOutOfStock = $product->status === 'out_of_stock' || (float) $product->current_stock <= 0;
            $newStock = (float) $product->current_stock + $quantity;

            $resolvedLocationId = $locationId;
            if ($resolvedLocationId) {
                $pivot = DB::table('product_storage_location')
                    ->where('product_id', $product->id)
                    ->where('storage_location_id', $resolvedLocationId)
                    ->first();

                if ($pivot) {
                    DB::table('product_storage_location')
                        ->where('id', $pivot->id)
                        ->update([
                            'current_stock' => (float) $pivot->current_stock + $quantity,
                            'updated_at'    => now(),
                        ]);
                } else {
                    DB::table('product_storage_location')->insert([
                        'product_id'          => $product->id,
                        'storage_location_id' => $resolvedLocationId,
                        'source_location_id'  => null,
                        'max_capacity'        => 100,
                        'current_stock'       => $quantity,
                        'restock_threshold'   => 10,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            } else {
                $resolvedLocationId = $this->addToStorageLocations($product, $quantity);
            }

            $this->logMovement($product, 'in', $quantity, $newStock, "Entrada via {$origin}", $originModel, $resolvedLocationId);

            $product->update(['current_stock' => $newStock]);

            if ($wasOutOfStock && $newStock > 0 && $product->status !== 'paused' && $product->status !== 'archived') {
                $this->triggerStockRestored($product);
            } else {
                $tenantId = tenant('id') ?? 'central';
                broadcast(new CatalogUpdated($tenantId));
            }
        });
    }

    public function deductStock(Product $product, float $qtySold, $originModel = null, ?string $description = null): void
    {
        if ($product->track_inventory) {
            $newStock = (float) $product->current_stock - $qtySold;
            $movementDesc = $description ?? "Saída operacional";

            $this->logMovement($product, 'out', -$qtySold, $newStock, $movementDesc, $originModel);

            // 🚀 OTIMIZAÇÃO: Atualização em massa em vez de requerer transações extras
            $product->updateQuietly(['current_stock' => $newStock]);
            $this->deductFromStorageLocations($product, $qtySold);

            if ($qtySold > 0) {
                // Removemos o fresh() que gerava mais uma query N+1; passamos os novos dados pelo objecto
                $product->current_stock = $newStock;
                $this->checkThresholds($product);
            }

            if ($newStock <= 0 && $product->status === 'available') {
                $this->triggerStockRupture($product);
            }
        }

        // 🚀 OTIMIZAÇÃO: Eager loading de 'ingredient' evita dezenas de queries find() na tabela de produtos
        $sheets = TechnicalSheet::with('ingredient')
            ->where('parent_product_id', $product->id)
            ->where('is_active', true)
            ->get();

        if ($sheets->isEmpty()) return;

        foreach ($sheets as $sheet) {
            $ingredient = $sheet->ingredient;
            if ($ingredient && $ingredient->track_inventory) {

                $ingQty = (float) $sheet->quantity * $qtySold;
                $newIngStock = (float) $ingredient->current_stock - $ingQty;

                $this->logMovement($ingredient, 'out', -$ingQty, $newIngStock, "Saída via Ficha Técnica", $originModel);

                $ingredient->updateQuietly(['current_stock' => $newIngStock]);
                $this->deductFromStorageLocations($ingredient, $ingQty);

                if ($qtySold > 0) {
                    $ingredient->current_stock = $newIngStock;
                    $this->checkThresholds($ingredient);
                }

                if ($newIngStock <= 0 && $ingredient->status === 'available') {
                    $this->triggerStockRupture($ingredient);
                }
            }
        }
    }

    public function deductLoss(InventoryLoss $loss): void
    {
        $loss->loadMissing('product');
        $product = $loss->product;

        if (!$product || !$product->track_inventory || (float) $loss->quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($product, $loss) {
            $qty = (float) $loss->quantity;
            $newStock = (float) $product->current_stock - $qty;

            $this->logMovement(
                product: $product,
                type: 'loss',
                quantity: -$qty,
                newBalance: $newStock,
                description: "Perda: {$loss->reason} ({$loss->formatted_type})",
                originModel: $loss,
                locationId: $loss->storage_location_id
            );

            $product->update(['current_stock' => $newStock]);

            if ($loss->storage_location_id) {
                $pivot = DB::table('product_storage_location')
                    ->where('product_id', $product->id)
                    ->where('storage_location_id', $loss->storage_location_id)
                    ->first();
                if ($pivot) {
                    $currentLoc = (float) $pivot->current_stock;
                    $product->storageLocations()->updateExistingPivot($loss->storage_location_id, [
                        'current_stock' => $currentLoc - $qty
                    ]);
                }
            } else {
                $this->deductFromStorageLocations($product, $qty);
            }

            if ($qty > 0) {
                $product->current_stock = $newStock;
                $this->checkThresholds($product);
            }

            if ($newStock <= 0 && $product->status === 'available') {
                $this->triggerStockRupture($product);
            }
        });
    }

    public function restoreLoss(InventoryLoss $loss): void
    {
        $loss->loadMissing('product');
        $product = $loss->product;

        if (!$product || !$product->track_inventory || (float) $loss->quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($product, $loss) {
            $qty = (float) $loss->quantity;
            $newStock = (float) $product->current_stock + $qty;

            $this->logMovement(
                product: $product,
                type: 'adjustment',
                quantity: $qty,
                newBalance: $newStock,
                description: "Estorno de Perda #{$loss->id}: {$loss->reason}",
                originModel: $loss,
                locationId: $loss->storage_location_id
            );

            $product->update(['current_stock' => $newStock]);

            if ($loss->storage_location_id) {
                $pivot = DB::table('product_storage_location')
                    ->where('product_id', $product->id)
                    ->where('storage_location_id', $loss->storage_location_id)
                    ->first();
                if ($pivot) {
                    $currentLoc = (float) $pivot->current_stock;
                    $product->storageLocations()->updateExistingPivot($loss->storage_location_id, [
                        'current_stock' => $currentLoc + $qty
                    ]);
                }
            } else {
                $this->addToStorageLocations($product, $qty);
            }

            if ($newStock > 0 && $product->status === 'out_of_stock') {
                $product->current_stock = $newStock;
                $this->triggerStockRestored($product);
            }
        });
    }

    public function restoreStock(Product $product, float $qtyReturned, $originModel = null): void
    {
        if ($product->track_inventory) {
            $newStock = (float) $product->current_stock + $qtyReturned;

            $this->logMovement($product, 'in', $qtyReturned, $newStock, "Estorno/Devolução", $originModel);

            $product->updateQuietly(['current_stock' => $newStock]);
            $this->addToStorageLocations($product, $qtyReturned);

            if ($newStock > 0 && $product->status === 'out_of_stock') {
                $product->current_stock = $newStock;
                $this->triggerStockRestored($product);
            }
        }

        $sheets = TechnicalSheet::with('ingredient')
            ->where('parent_product_id', $product->id)
            ->where('is_active', true)
            ->get();

        if ($sheets->isEmpty()) return;

        foreach ($sheets as $sheet) {
            $ingredient = $sheet->ingredient;
            if ($ingredient && $ingredient->track_inventory) {
                $ingQty = (float) $sheet->quantity * $qtyReturned;
                $newIngStock = (float) $ingredient->current_stock + $ingQty;

                $this->logMovement($ingredient, 'in', $ingQty, $newIngStock, "Estorno via Ficha Técnica", $originModel);

                $ingredient->updateQuietly(['current_stock' => $newIngStock]);
                $this->addToStorageLocations($ingredient, $ingQty);

                if ($newIngStock > 0 && $ingredient->status === 'out_of_stock') {
                    $ingredient->current_stock = $newIngStock;
                    $this->triggerStockRestored($ingredient);
                }
            }
        }
    }

    public function processOrderItemStock(OrderItem $item, string $action = 'deduct'): void
    {
        $multiplier = $action === 'restore' ? -1 : 1;

        if ($item->product_id) {
            $product = Product::find($item->product_id);
            if ($product) {
                if ($multiplier === 1) {
                    $this->deductStock($product, $item->quantity, $item);
                } else {
                    $this->restoreStock($product, $item->quantity, $item);
                }
            }
        }

        foreach ($item->modifiers as $mod) {
            if ($mod->modifier_option_id) {
                $opt = ModifierOption::find($mod->modifier_option_id);
                if ($opt && $opt->linked_product_id) {
                    $linked = Product::find($opt->linked_product_id);
                    $qtyToProcess = $opt->linked_qty * $mod->quantity;
                    if ($linked) {
                        if ($multiplier === 1) $this->deductStock($linked, $qtyToProcess, $item);
                        else $this->restoreStock($linked, $qtyToProcess, $item);
                    }
                }
            }
        }

        foreach ($item->subItems as $sub) {
            if ($sub->product_id) {
                $subProd = Product::find($sub->product_id);
                if ($subProd) {
                    if ($multiplier === 1) $this->deductStock($subProd, $sub->quantity, $item);
                    else $this->restoreStock($subProd, $sub->quantity, $item);
                }
            }
            foreach ($sub->modifiers as $mod) {
                if ($mod->modifier_option_id) {
                    $opt = ModifierOption::find($mod->modifier_option_id);
                    if ($opt && $opt->linked_product_id) {
                        $linked = Product::find($opt->linked_product_id);
                        $qtyToProcess = $opt->linked_qty * $mod->quantity;
                        if ($linked) {
                            if ($multiplier === 1) $this->deductStock($linked, $qtyToProcess, $item);
                            else $this->restoreStock($linked, $qtyToProcess, $item);
                        }
                    }
                }
            }
        }
    }

    public function adjustStock(Product $product, float $newStock, string $reason, ?int $userId = null): void
    {
        DB::transaction(function () use ($product, $newStock, $reason, $userId) {
            $oldStock = (float) $product->current_stock;
            $diff = $newStock - $oldStock;

            $this->logMovement($product, 'adjustment', $diff, $newStock, "Ajuste: {$reason}");

            $product->update(['current_stock' => $newStock]);

            if ($diff < 0) {
                $this->deductFromStorageLocations($product, abs($diff));
                $this->registerLoss(
                    product: $product,
                    qty: abs($diff),
                    reason: 'Ajuste Manual: ' . $reason,
                    notes: "Estoque alterado de {$oldStock} para {$newStock}",
                    userId: $userId,
                    orderItemId: null,
                    lossType: InventoryLoss::TYPE_INVENTORY_AUDIT,
                    storageLocationId: null,
                    skipDeduction: true
                );
            } elseif ($diff > 0) {
                $this->addToStorageLocations($product, $diff);
            }

            if ($newStock <= 0) {
                if ($product->status === 'available') {
                    $this->triggerStockRupture($product);
                }
            } else {
                if ($product->status === 'out_of_stock') {
                    $this->triggerStockRestored($product);
                } else {
                    $this->checkThresholds($product);
                    broadcast(new CatalogUpdated(tenant('id') ?? 'central'));
                }
            }
        });
    }

    public function batchAdjust(array $items, string $reason, int $userId): array
    {
        $lossesCount = 0;
        $totalLossAmount = 0.0;

        DB::transaction(function () use ($items, $userId, $reason, &$lossesCount, &$totalLossAmount) {
            foreach ($items as $entry) {
                \Illuminate\Support\Facades\Log::info("🔍 [PROBE ITEM PROCESSANDO]", $entry);
                $product = Product::find($entry['product_id']);
                if (!$product || !$product->track_inventory) {
                    continue;
                }

                $locationId = !empty($entry['location_id']) ? (int) $entry['location_id'] : null;
                $newLocationStock = (float) $entry['counted_stock'];

                if ($locationId) {
                    $pivot = DB::table('product_storage_location')
                        ->where('product_id', $product->id)
                        ->where('storage_location_id', $locationId)
                        ->first();

                    $oldLocationStock = $pivot ? (float) $pivot->current_stock : 0.0;
                    $locationDelta = round($newLocationStock - $oldLocationStock, 4);

                    DB::table('product_storage_location')->updateOrInsert(
                        ['product_id' => $product->id, 'storage_location_id' => $locationId],
                        ['current_stock' => $newLocationStock, 'updated_at' => now()]
                    );

                    $sumLocations = (float) DB::table('product_storage_location')
                        ->where('product_id', $product->id)
                        ->sum('current_stock');

                    $loc = StorageLocation::find($locationId);
                    $locName = $loc?->name ?? "Local #{$locationId}";

                    // Registro de Kardex
                    $this->logMovement(
                        product: $product,
                        type: 'adjustment',
                        quantity: $locationDelta,
                        newBalance: $sumLocations,
                        description: "Balanço Mobile ({$locName}): {$reason}",
                        locationId: $locationId
                    );

                    $product->update(['current_stock' => $sumLocations]);
                    \Illuminate\Support\Facades\Log::info("🔍 [PROBE SALDO ATUALIZADO]", [
                        "product_id" => $product->id,
                        "product_name" => $product->name,
                        "location_id" => $locationId,
                        "newLocationStock" => $newLocationStock,
                        "sumLocations" => $sumLocations,
                        "product_current_stock_before_fresh" => $product->current_stock
                    ]);

                    if ($locationDelta < 0) {
                        $lossQty = abs($locationDelta);

                        $lossRecord = $this->registerLoss(
                            product: $product,
                            qty: $lossQty,
                            reason: "Furo de Balanço: {$locName}",
                            notes: "Contagem física: {$newLocationStock} {$product->unit_of_measure} (Anterior: {$oldLocationStock}). Motivo: {$reason}",
                            userId: $userId,
                            orderItemId: null,
                            lossType: InventoryLoss::TYPE_INVENTORY_AUDIT,
                            storageLocationId: $locationId,
                            skipDeduction: true
                        );

                        $lossesCount++;
                        $totalLossAmount += (float) $lossRecord->total_cost;
                    }
                } else {
                    $oldStock = (float) $product->current_stock;
                    $delta = round($newLocationStock - $oldStock, 4);

                    $this->adjustStock($product, $newLocationStock, $reason, $userId);

                    if ($delta < 0) {
                        $lossesCount++;
                    }
                }

                // Avaliação de Ruptura ou Restauração
                $freshStock = (float) $product->fresh()->current_stock;
                if ($freshStock <= 0 && $product->status === 'available') {
                    $this->triggerStockRupture($product);
                } elseif ($freshStock > 0 && $product->status === 'out_of_stock') {
                    $this->triggerStockRestored($product);
                }
            }
        });

        broadcast(new CatalogUpdated(function_exists('tenant') && tenant('id') ? tenant('id') : 'central'));

        return [
            'losses_count'      => $lossesCount,
            'total_loss_amount' => $totalLossAmount,
        ];
    }

    public function registerLoss(
        Product $product,
        float $qty,
        string $reason,
        string $notes,
        ?int $userId = null,
        ?int $orderItemId = null,
        string $lossType = InventoryLoss::TYPE_OPERATIONAL,
        ?int $storageLocationId = null,
        bool $skipDeduction = false
    ): InventoryLoss {
        $unitCost = class_exists(\App\Services\SupplyChain\ProductCostingService::class)
            ? \App\Services\SupplyChain\ProductCostingService::calculateBaseCost($product)
            : (float) ($product->base_cost ?? 0);

        $loss = new InventoryLoss([
            'product_id'          => $product->id,
            'order_item_id'       => $orderItemId,
            'user_id'             => $userId,
            'storage_location_id' => $storageLocationId,
            'loss_type'           => $lossType,
            'quantity'            => $qty,
            'unit_cost'           => $unitCost,
            'total_cost'          => round($unitCost * $qty, 5),
            'reason'              => $reason,
            'notes'               => $notes,
        ]);

        $loss->skipInventoryDeduction = $skipDeduction;
        $loss->save();

        return $loss;
    }

    public function auditStorageLocation(int $locationId, array $countedItems, int $userId): array
    {
        $location = StorageLocation::findOrFail($locationId);
        $divergences = [];
        $totalLossBrl = 0.0;

        DB::transaction(function () use ($location, $countedItems, $userId, &$divergences, &$totalLossBrl) {
            foreach ($countedItems as $entry) {
                $productId = (int) $entry['product_id'];
                $countedQty = (float) $entry['counted_stock'];

                $product = Product::find($productId);
                if (!$product || !$product->track_inventory) continue;

                $pivotData = DB::table('product_storage_location')
                    ->where('product_id', $productId)
                    ->where('storage_location_id', $location->id)
                    ->first();

                $localSystemQty = $pivotData ? (float) $pivotData->current_stock : 0;
                $delta = round($countedQty - $localSystemQty, 4);

                if ($delta === 0.0) continue;

                if ($pivotData) {
                    $product->storageLocations()->updateExistingPivot($location->id, ['current_stock' => $countedQty]);
                } else {
                    $product->storageLocations()->attach($location->id, ['current_stock' => $countedQty]);
                }

                $newGlobalStock = (float) DB::table('product_storage_location')
                    ->where('product_id', $productId)
                    ->sum('current_stock');

                $this->logMovement($product, 'adjustment', $delta, $newGlobalStock, "Balanço Local: {$location->name}", null, $location->id);

                $product->update(['current_stock' => $newGlobalStock]);

                if ($delta < 0) {
                    $lossQty = abs($delta);
                    $lossRecord = $this->registerLoss(
                        product: $product,
                        qty: $lossQty,
                        reason: "Furo de Balanço: {$location->name}",
                        notes: "Contagem física: {$countedQty}. Sistema esperava: {$localSystemQty}.",
                        userId: $userId,
                        orderItemId: null,
                        lossType: InventoryLoss::TYPE_INVENTORY_AUDIT,
                        storageLocationId: $location->id,
                        skipDeduction: true
                    );

                    if ($newGlobalStock <= 0) {
                        $this->triggerStockRupture($product);
                    }

                    $totalLossBrl += (float) $lossRecord->total_cost;
                    $divergences[] = [
                        'product' => $product->name,
                        'type'    => 'loss',
                        'delta'   => $delta,
                        'uom'     => $product->unit_of_measure,
                        'cost'    => (float) $lossRecord->total_cost
                    ];
                } elseif ($delta > 0) {
                    if ($product->status === 'out_of_stock' && $newGlobalStock > 0) {
                        $this->triggerStockRestored($product);
                    }
                    $divergences[] = [
                        'product' => $product->name,
                        'type'    => 'surplus',
                        'delta'   => $delta,
                        'uom'     => $product->unit_of_measure,
                        'cost'    => 0
                    ];
                }
            }
        });

        broadcast(new CatalogUpdated(tenant('id') ?? 'central'));

        return [
            'location'       => $location->name,
            'divergences'    => $divergences,
            'total_loss_brl' => round($totalLossBrl, 2),
        ];
    }

    public function triggerStockRupture(Product $product): void
    {
        $product->updateQuietly(['status' => 'out_of_stock']);

        ModifierOption::where('linked_product_id', $product->id)->update(['is_active' => false]);

        $parentIds = TechnicalSheet::where('ingredient_product_id', $product->id)->where('is_active', true)->pluck('parent_product_id')->unique();
        if ($parentIds->isNotEmpty()) {
            Product::whereIn('id', $parentIds)->whereNotIn('status', ['paused', 'archived'])->update(['status' => 'out_of_stock']);
        }

        event(new StockLowDetected($product, 'critical'));
        broadcast(new CatalogUpdated(tenant('id') ?? 'central'));

        Log::warning("🚨 [Estoque Ruptura] '{$product->name}' esgotou. Adicionais e pratos bloqueados.");
    }

    public function triggerStockRestored(Product $product): void
    {
        if (in_array($product->status, ['paused', 'archived'], true)) {
            return;
        }

        $hasMissingIngredients = TechnicalSheet::where('parent_product_id', $product->id)->where('is_active', true)
            ->whereHas('ingredient', function ($query) {
                $query->whereIn('status', ['out_of_stock', 'unavailable', 'paused', 'archived']);
            })
            ->exists();

        if (!$hasMissingIngredients) {
            $product->updateQuietly(['status' => 'available']);
        }

        ModifierOption::where('linked_product_id', $product->id)->update(['is_active' => true]);

        $parentIds = TechnicalSheet::where('ingredient_product_id', $product->id)->where('is_active', true)->pluck('parent_product_id')->unique();
        foreach ($parentIds as $parentId) {
            $parent = Product::find($parentId);
            if (!$parent || in_array($parent->status, ['paused', 'archived'], true)) continue;

            $parentMissing = TechnicalSheet::where('parent_product_id', $parentId)->where('is_active', true)
                ->whereHas('ingredient', fn($q) => $q->whereIn('status', ['out_of_stock', 'unavailable', 'paused', 'archived']))
                ->exists();

            $parentPhysical = $parent->track_inventory && (float) $parent->current_stock <= 0;

            if (!$parentMissing && !$parentPhysical) {
                $parent->updateQuietly(['status' => 'available']);
            }
        }

        broadcast(new CatalogUpdated(tenant('id') ?? 'central'));
    }

    private function checkThresholds(Product $product): void
    {
        if ($product->min_stock !== null && $product->current_stock <= $product->min_stock) {
            Log::info("⚠️ [Estoque Baixo] Produto {$product->name} atingiu o limite mínimo.");
            event(new StockLowDetected($product, $product->current_stock <= 0 ? 'critical' : 'normal'));
        }
    }
}
