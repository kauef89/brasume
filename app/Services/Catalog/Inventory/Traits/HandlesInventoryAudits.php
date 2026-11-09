<?php
namespace App\Services\Catalog\Inventory\Traits;

use App\Models\InventoryLoss;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Events\CatalogUpdated;
use Illuminate\Support\Facades\DB;

trait HandlesInventoryAudits
{
    public function adjustStock(Product $product, float $newStock, string $reason, ?int $userId = null): void
    {
        DB::transaction(function () use ($product, $newStock, $reason, $userId) {
            $oldStock = (float) $product->current_stock;
            $diff = $newStock - $oldStock;

            $this->logMovement($product, 'adjustment', $diff, $newStock, "Ajuste: {$reason}");
            $product->update(['current_stock' => $newStock]);

            if ($diff < 0) {
                $this->deductFromStorageLocations($product, abs($diff));
                $this->registerLoss($product, abs($diff), 'Ajuste Manual: ' . $reason, "Alterado de {$oldStock} para {$newStock}", $userId, null, InventoryLoss::TYPE_INVENTORY_AUDIT, null, true);
            } elseif ($diff > 0) {
                $this->addToStorageLocations($product, $diff);
            }

            if ($newStock <= 0 && $product->status === 'available') {
                $this->triggerStockRupture($product);
            } elseif ($newStock > 0 && $product->status === 'out_of_stock') {
                $this->triggerStockRestored($product);
            } else {
                $this->checkThresholds($product);
                broadcast(new CatalogUpdated(tenant('id') ?? 'central'));
            }
        });
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
            $qty = (float) $loss->quantity;
            $newStock = (float) $product->current_stock - $qty;
            $this->logMovement($product, 'loss', -$qty, $newStock, "Perda: {$loss->reason}", $loss, $loss->storage_location_id);
            $product->update(['current_stock' => $newStock]);
            
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
            if ($newStock <= 0 && $product->status === 'available') $this->triggerStockRupture($product);
        });
    }

    public function restoreLoss(InventoryLoss $loss): void
    {
        $loss->loadMissing('product');
        $product = $loss->product;
        if (!$product || !$product->track_inventory || (float) $loss->quantity <= 0) return;

        DB::transaction(function () use ($product, $loss) {
            $qty = (float) $loss->quantity;
            $newStock = (float) $product->current_stock + $qty;
            $this->logMovement($product, 'adjustment', $qty, $newStock, "Estorno de Perda #{$loss->id}: {$loss->reason}", $loss, $loss->storage_location_id);
            $product->update(['current_stock' => $newStock]);

            if ($loss->storage_location_id) {
                $pivot = DB::table('product_storage_locations')->where('product_id', $product->id)->where('storage_location_id', $loss->storage_location_id)->first();
                if ($pivot) $product->storageLocations()->updateExistingPivot($loss->storage_location_id, ['current_stock' => (float) $pivot->current_stock + $qty]);
            } else {
                $this->addToStorageLocations($product, $qty);
            }
            if ($newStock > 0 && $product->status === 'out_of_stock') {
                $product->current_stock = $newStock;
                $this->triggerStockRestored($product);
            }
        });
    }
}
