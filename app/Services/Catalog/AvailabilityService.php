<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\TechnicalSheet;
use App\Models\ModifierOption;
use App\Models\ShoppingListItem;
use App\Events\CatalogUpdated;
use App\Events\StockLowDetected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AvailabilityService
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function updateAvailability(int $productId, string $action, ?float $newStock = null): void
    {
        DB::transaction(function () use ($productId, $action, $newStock) {
            $product = Product::findOrFail($productId);
            $visitedIds = [];

            if ($action === 'out_of_stock') {
                $product->update(['status' => 'out_of_stock']);

                if ($product->track_inventory) {
                    $product->update(['current_stock' => 0]);
                    event(new StockLowDetected($product, 'critical'));
                }

                // Desativa adicionais vinculados a este insumo
                ModifierOption::where('linked_product_id', $product->id)->update(['is_active' => false]);

                // Cascateia indisponibilidade para pratos que usam este insumo
                $this->cascadeDisable($product->id, $visitedIds);

            } elseif ($action === 'paused') {
                $product->update(['status' => 'paused']);

                // Pausa gerencial também desativa modificadores e pratos dependentes
                ModifierOption::where('linked_product_id', $product->id)->update(['is_active' => false]);
                $this->cascadeDisable($product->id, $visitedIds);

            } elseif ($action === 'archived') {
                $product->update(['status' => 'archived']);

                ModifierOption::where('linked_product_id', $product->id)->update(['is_active' => false]);
                $this->cascadeDisable($product->id, $visitedIds);

            } elseif ($action === 'available') {
                $updates = ['status' => 'available'];

                if ($product->track_inventory && !is_null($newStock)) {
                    $updates['current_stock'] = $newStock;

                    ShoppingListItem::where('product_id', $product->id)
                        ->whereIn('status', ['pending', 'ordered'])
                        ->update(['status' => 'received']);
                }

                $product->update($updates);

                // Reativa modificadores vinculados
                ModifierOption::where('linked_product_id', $product->id)->update(['is_active' => true]);

                // Reavalia pratos dependentes na ficha técnica
                $this->cascadeEnable($product->id, $visitedIds);
            }
        });

        Cache::tags(['catalog'])->flush();

        $tenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        broadcast(new CatalogUpdated($tenant));
    }

    public function toggleProductStatus(int $productId, string $newStatus): void
    {
        $action = match ($newStatus) {
            'out_of_stock', 'unavailable' => 'out_of_stock',
            'paused'                      => 'paused',
            'archived'                    => 'archived',
            default                       => 'available'
        };

        $this->updateAvailability($productId, $action);
    }

    private function cascadeDisable(int $ingredientId, array &$visitedIds): void
    {
        if (in_array($ingredientId, $visitedIds)) return;
        $visitedIds[] = $ingredientId;

        $parentIds = TechnicalSheet::where('ingredient_product_id', $ingredientId)
            ->where('is_active', true)
            ->pluck('parent_product_id')
            ->unique();

        if ($parentIds->isNotEmpty()) {
            Product::whereIn('id', $parentIds)
                ->where('status', '!=', 'paused')
                ->where('status', '!=', 'archived')
                ->update(['status' => 'out_of_stock']);

            foreach ($parentIds as $parentId) {
                $this->cascadeDisable($parentId, $visitedIds);
            }
        }
    }

    private function cascadeEnable(int $ingredientId, array &$visitedIds): void
    {
        if (in_array($ingredientId, $visitedIds)) return;
        $visitedIds[] = $ingredientId;

        $parentIds = TechnicalSheet::where('ingredient_product_id', $ingredientId)
            ->where('is_active', true)
            ->pluck('parent_product_id')
            ->unique();

        foreach ($parentIds as $parentId) {
            $parentProduct = Product::find($parentId);
            if (!$parentProduct) continue;

            $hasMissingIngredients = TechnicalSheet::where('parent_product_id', $parentId)->where('is_active', true)
                ->whereHas('ingredient', function ($query) {
                    $query->whereIn('status', ['out_of_stock', 'unavailable', 'paused', 'archived']);
                })
                ->exists();

            $isPhysicallyBlocked = $parentProduct->track_inventory && (float) $parentProduct->current_stock <= 0;
            $isManuallyBlocked = in_array($parentProduct->status, ['paused', 'archived'], true);

            if (!$hasMissingIngredients && !$isPhysicallyBlocked && !$isManuallyBlocked) {
                $parentProduct->update(['status' => 'available']);
                $this->cascadeEnable($parentId, $visitedIds);
            }
        }
    }

    public function registerLoss(array $data, int $userId): void
    {
        $product = Product::findOrFail($data['target_id']);

        $this->inventoryService->registerLoss(
            $product,
            (float) $data['quantity'],
            'Salão/Bar: ' . ($data['reason'] ?? 'Desperdício informado'),
            'Registrado via Módulo Operacional.',
            $userId
        );
    }
}
