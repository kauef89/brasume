<?php
namespace App\Services\Catalog\Inventory\Traits;

use App\Models\InventoryLoss;
use App\Models\Product;
use App\Models\ProductStorageLocation;
use App\Models\StorageLocation;
use App\Events\CatalogUpdated;
use Illuminate\Support\Facades\DB;

trait HandlesInventoryAudits
{
    public function adjustStock(Product $product, float $newStock, string $reason, ?int $userId = null): void
    {
        DB::transaction(function () use ($product, $newStock, $reason, $userId) {
            ProductStorageLocation::$skipAutoKardex = true;
            try {
                $oldStock = (float) $product->current_stock;
                $diff = round($newStock - $oldStock, 4);

                $this->logMovement($product, 'adjustment', $diff, $newStock, "Ajuste: {$reason}", null, null, $userId);
                $product->updateQuietly(['current_stock' => $newStock]);

                if ($diff < 0) {
                    $this->deductFromStorageLocations($product, abs($diff));
                    $this->registerLoss($product, abs($diff), 'Ajuste Manual: ' . $reason, "Alterado de {$oldStock} para {$newStock}", $userId, null, InventoryLoss::TYPE_INVENTORY_AUDIT, null, true);
                } elseif ($diff > 0) {
                    $this->addToStorageLocations($product, $diff);
                }

                $this->syncProductStatusAfterAudit($product, $newStock);
            } finally {
                ProductStorageLocation::$skipAutoKardex = false;
            }
        });
    }

    /**
     * Processa o Balanço Físico Operacional (TeamApp) respeitando o local contado.
     * Nunca sobrescreve o saldo global da loja com a contagem de um único local.
     */
    public function batchAdjust(array $items, string $reason, ?int $userId = null): array
    {
        $lossesCount = 0;
        $transfersSummary = [];

        DB::transaction(function () use ($items, $reason, $userId, &$lossesCount, &$transfersSummary) {
            ProductStorageLocation::$skipAutoKardex = true;
            try {
                foreach ($items as $entry) {
                    $product = Product::lockForUpdate()->find($entry['product_id']);
                    if (!$product || !$product->track_inventory) {
                        continue;
                    }

                    $countedStock = round((float) $entry['counted_stock'], 4);
                    $locationId   = !empty($entry['location_id']) ? (int) $entry['location_id'] : null;
                    $oldGlobal    = (float) $product->current_stock;

                    if ($locationId) {
                        $location = StorageLocation::find($locationId);
                        $locName  = $location?->name ?? "Local #{$locationId}";

                        $pivot = DB::table('product_storage_locations')
                            ->where('product_id', $product->id)
                            ->where('storage_location_id', $locationId)
                            ->lockForUpdate()
                            ->first();

                        $oldLocStock = $pivot ? (float) $pivot->current_stock : 0.0;
                        $locDiff     = round($countedStock - $oldLocStock, 4);

                        if (abs($locDiff) < 0.0001) {
                            continue;
                        }

                        if ($pivot) {
                            DB::table('product_storage_locations')
                                ->where('id', $pivot->id)
                                ->update([
                                    'current_stock' => $countedStock,
                                    'updated_at'    => now(),
                                ]);
                        } else {
                            DB::table('product_storage_locations')->insert([
                                'product_id'          => $product->id,
                                'storage_location_id' => $locationId,
                                'current_stock'       => $countedStock,
                                'created_at'          => now(),
                                'updated_at'          => now(),
                            ]);
                        }

                        // Recalcula o estoque global como a SOMA REAL de todos os locais do produto
                        $newGlobal = round((float) DB::table('product_storage_locations')
                            ->where('product_id', $product->id)
                            ->sum('current_stock'), 4);

                        $this->logMovement(
                            $product,
                            'adjustment',
                            $locDiff,
                            $newGlobal,
                            "{$reason} [{$locName}: {$oldLocStock} → {$countedStock}]",
                            null,
                            $locationId,
                            $userId
                        );

                        $product->updateQuietly(['current_stock' => $newGlobal]);

                        if ($locDiff < 0) {
                            $lossesCount++;
                            $this->registerLoss(
                                $product,
                                abs($locDiff),
                                "Divergência Balanço ({$locName})",
                                "{$reason} | Local alterado de {$oldLocStock} para {$countedStock}",
                                $userId,
                                null,
                                InventoryLoss::TYPE_INVENTORY_AUDIT,
                                $locationId,
                                true
                            );
                        }

                        $this->syncProductStatusAfterAudit($product, $newGlobal);
                    } else {
                        // Contagem Geral (sem local específico selecionado)
                        $diff = round($countedStock - $oldGlobal, 4);
                        if (abs($diff) < 0.0001) {
                            continue;
                        }

                        $this->logMovement($product, 'adjustment', $diff, $countedStock, $reason, null, null, $userId);
                        $product->updateQuietly(['current_stock' => $countedStock]);

                        if ($diff < 0) {
                            $lossesCount++;
                            $this->deductFromStorageLocations($product, abs($diff));
                            $this->registerLoss(
                                $product,
                                abs($diff),
                                'Divergência Balanço Geral',
                                "{$reason} | Saldo alterado de {$oldGlobal} para {$countedStock}",
                                $userId,
                                null,
                                InventoryLoss::TYPE_INVENTORY_AUDIT,
                                null,
                                true
                            );
                        } else {
                            $this->addToStorageLocations($product, $diff);
                        }

                        $this->syncProductStatusAfterAudit($product, $countedStock);
                    }
                }
            } finally {
                ProductStorageLocation::$skipAutoKardex = false;
            }
        });

        return [
            'losses_count'      => $lossesCount,
            'transfers_summary' => $transfersSummary,
        ];
    }

    private function syncProductStatusAfterAudit(Product $product, float $newStock): void
    {
        $statusVal = $product->status instanceof \BackedEnum ? $product->status->value : (string) $product->status;
        $product->current_stock = $newStock;

        if ($newStock <= 0 && $statusVal === 'available') {
            $this->triggerStockRupture($product);
        } elseif ($newStock > 0 && $statusVal === 'out_of_stock') {
            $this->triggerStockRestored($product);
        } else {
            $this->checkThresholds($product);
            broadcast(new CatalogUpdated(tenant('id') ?? 'central'));
        }
    }

    public function registerLoss(Product $product, float $qty, string $reason, string $notes, ?int $userId = null, ?int $orderItemId = null, string $lossType = InventoryLoss::TYPE_OPERATIONAL, ?int $storageLocationId = null, bool $skipDeduction = false): InventoryLoss
    {
        $unitCost = (float) ($product->base_cost ?? 0);
        $loss = new InventoryLoss([
            'product_id' => $product->id, 'order_item_id' => $orderItemId, 'user_id' => $userId,
            'storage_location_id' => $storageLocationId, 'loss_type' => $lossType,
            'quantity' => $qty, 'unit_cost' => $unitCost, 'total_cost' => round($unitCost * $qty, 5),
            'reason' => $reason, 'notes' => $notes,
        ]);
        $loss->skipInventoryDeduction = $skipDeduction;
        $loss->save();
        return $loss;
    }

    public function deductLoss(InventoryLoss $loss): void
    {
        $loss->loadMissing('product');
        $product = $loss->product;
        if (!$product || !$product->track_inventory || (float) $loss->quantity <= 0) return;

        DB::transaction(function () use ($product, $loss) {
            ProductStorageLocation::$skipAutoKardex = true;
            try {
                $qty = (float) $loss->quantity;
                $newStock = (float) $product->current_stock - $qty;
                $this->logMovement($product, 'loss', -$qty, $newStock, "Perda: {$loss->reason}", $loss, $loss->storage_location_id, $loss->user_id);
                $product->updateQuietly(['current_stock' => $newStock]);

                if ($loss->storage_location_id) {
                    $pivot = DB::table('product_storage_locations')->where('product_id', $product->id)->where('storage_location_id', $loss->storage_location_id)->first();
                    if ($pivot) $product->storageLocations()->updateExistingPivot($loss->storage_location_id, ['current_stock' => (float) $pivot->current_stock - $qty]);
                } else {
                    $this->deductFromStorageLocations($product, $qty);
                }

                if ($qty > 0) {
                    $product->current_stock = $newStock;
                    $this->checkThresholds($product);
                }
                $statusVal = $product->status instanceof \BackedEnum ? $product->status->value : (string) $product->status;
                if ($newStock <= 0 && $statusVal === 'available') $this->triggerStockRupture($product);
            } finally {
                ProductStorageLocation::$skipAutoKardex = false;
            }
        });
    }

    public function restoreLoss(InventoryLoss $loss): void
    {
        $loss->loadMissing('product');
        $product = $loss->product;
        if (!$product || !$product->track_inventory || (float) $loss->quantity <= 0) return;

        DB::transaction(function () use ($product, $loss) {
            ProductStorageLocation::$skipAutoKardex = true;
            try {
                $qty = (float) $loss->quantity;
                $newStock = (float) $product->current_stock + $qty;
                $this->logMovement($product, 'adjustment', $qty, $newStock, "Estorno de Perda #{$loss->id}: {$loss->reason}", $loss, $loss->storage_location_id, $loss->user_id);
                $product->updateQuietly(['current_stock' => $newStock]);

                if ($loss->storage_location_id) {
                    $pivot = DB::table('product_storage_locations')->where('product_id', $product->id)->where('storage_location_id', $loss->storage_location_id)->first();
                    if ($pivot) $product->storageLocations()->updateExistingPivot($loss->storage_location_id, ['current_stock' => (float) $pivot->current_stock + $qty]);
                } else {
                    $this->addToStorageLocations($product, $qty);
                }
                $statusVal = $product->status instanceof \BackedEnum ? $product->status->value : (string) $product->status;
                if ($newStock > 0 && $statusVal === 'out_of_stock') {
                    $product->current_stock = $newStock;
                    $this->triggerStockRestored($product);
                }
            } finally {
                ProductStorageLocation::$skipAutoKardex = false;
            }
        });
    }
}