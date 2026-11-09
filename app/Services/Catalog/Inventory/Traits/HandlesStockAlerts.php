<?php
namespace App\Services\Catalog\Inventory\Traits;

use App\Models\Product;
use App\Models\ModifierOption;
use App\Models\TechnicalSheet;
use App\Events\StockLowDetected;
use App\Events\CatalogUpdated;

trait HandlesStockAlerts
{
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
    }

    public function triggerStockRestored(Product $product): void
    {
        if (in_array($product->status, ['paused', 'archived'], true)) return;

        $hasMissingIngredients = TechnicalSheet::where('parent_product_id', $product->id)->where('is_active', true)
            ->whereHas('ingredient', fn ($q) => $q->whereIn('status', ['out_of_stock', 'unavailable', 'paused', 'archived']))
            ->exists();

        if (!$hasMissingIngredients) $product->updateQuietly(['status' => 'available']);
        ModifierOption::where('linked_product_id', $product->id)->update(['is_active' => true]);

        $parentIds = TechnicalSheet::where('ingredient_product_id', $product->id)->where('is_active', true)->pluck('parent_product_id')->unique();
        foreach ($parentIds as $parentId) {
            $parent = Product::find($parentId);
            if (!$parent || in_array($parent->status, ['paused', 'archived'], true)) continue;

            $parentMissing = TechnicalSheet::where('parent_product_id', $parentId)->where('is_active', true)
                ->whereHas('ingredient', fn ($q) => $q->whereIn('status', ['out_of_stock', 'unavailable', 'paused', 'archived']))
                ->exists();

            if (!$parentMissing && !($parent->track_inventory && (float) $parent->current_stock <= 0)) {
                $parent->updateQuietly(['status' => 'available']);
            }
        }
        broadcast(new CatalogUpdated(tenant('id') ?? 'central'));
    }

    private function checkThresholds(Product $product): void
    {
        if ($product->min_stock !== null && $product->current_stock <= $product->min_stock) {
            event(new StockLowDetected($product, $product->current_stock <= 0 ? 'critical' : 'normal'));
        }
    }
}
