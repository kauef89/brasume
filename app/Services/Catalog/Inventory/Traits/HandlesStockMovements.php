<?php
namespace App\Services\Catalog\Inventory\Traits;

use App\Models\Product;
use App\Events\CatalogUpdated;
use Illuminate\Support\Facades\DB;

trait HandlesStockMovements
{
    private function deductFromStorageLocations(Product $product, float $qty): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $locations = $product->storageLocations()
            ->when(!$isSqlite, fn ($query) => $query->orderByRaw("FIELD(type, 'display', 'intermediate', 'storage')"))
            ->when($isSqlite, fn ($query) => $query->orderBy('sort_order'))
            ->get();

        foreach ($locations as $location) {
            if ($qty <= 0) break;
            $stock = (float) $location->pivot->current_stock;
            if ($stock > 0) {
                $take = min($stock, $qty);
                $product->storageLocations()->updateExistingPivot($location->id, ['current_stock' => $stock - $take]);
                $qty -= $take;
            }
        }
    }

    private function addToStorageLocations(Product $product, float $qtyToAdd): ?int
    {
        if (!$product->relationLoaded('storageLocations') && DB::table('product_storage_locations')->where('product_id', $product->id)->count() === 0) return null;
        $locations = $product->storageLocations()->orderByRaw("FIELD(type, 'storage', 'intermediate', 'display')")->get();
        if ($locations->isEmpty()) return null;

        $targetLoc = $locations->first();
        $product->storageLocations()->updateExistingPivot($targetLoc->id, ['current_stock' => (float) $targetLoc->pivot->current_stock + $qtyToAdd]);
        return (int) $targetLoc->id;
    }

    public function logMovement(Product $product, string $type, float $quantity, float $newBalance, string $description, $originModel = null, ?int $locationId = null, ?int $userId = null): void
    {
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
            'user_id'             => $userId ?? auth()->id(),
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
                $pivot = DB::table('product_storage_locations')->where('product_id', $product->id)->where('storage_location_id', $resolvedLocationId)->first();
                if ($pivot) {
                    DB::table('product_storage_locations')->where('id', $pivot->id)->update(['current_stock' => (float) $pivot->current_stock + $quantity, 'updated_at' => now()]);
                } else {
                    DB::table('product_storage_locations')->insert(['product_id' => $product->id, 'storage_location_id' => $resolvedLocationId, 'current_stock' => $quantity, 'created_at' => now(), 'updated_at' => now()]);
                }
            } else {
                $resolvedLocationId = $this->addToStorageLocations($product, $quantity);
            }

            $this->logMovement($product, 'in', $quantity, $newStock, "Entrada via {$origin}", $originModel, $resolvedLocationId);
            $product->update(['current_stock' => $newStock]);

            if ($wasOutOfStock && $newStock > 0 && $product->status !== 'paused' && $product->status !== 'archived') {
                $this->triggerStockRestored($product);
            } else {
                broadcast(new CatalogUpdated(tenant('id') ?? 'central'));
            }
        });
    }

    public function deductStock(Product $product, float $qtySold, $originModel = null, ?string $description = null): void
    {
        if ($product->track_inventory) {
            $newStock = (float) $product->current_stock - $qtySold;
            $this->logMovement($product, 'out', -$qtySold, $newStock, $description ?? "Saída operacional", $originModel);
            $product->updateQuietly(['current_stock' => $newStock]);
            $this->deductFromStorageLocations($product, $qtySold);

            if ($qtySold > 0) {
                $product->current_stock = $newStock;
                $this->checkThresholds($product);
            }
            if ($newStock <= 0 && $product->status === 'available') $this->triggerStockRupture($product);
        }
        $this->deductRecipeStock($product, $qtySold, $originModel);
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
        $this->restoreRecipeStock($product, $qtyReturned, $originModel);
    }
}
