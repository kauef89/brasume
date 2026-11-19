<?php
namespace App\Services\Catalog\Costing;

use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\TechnicalSheet;

class BaseCostCalculator
{
    private static array $costCache = [];

    public static function clearCache(): void
    {
        self::$costCache = [];
    }

    public static function calculate(Product $product, string $consumptionMode = "all"): float
    {
        $cacheKey = $product->id . '_' . $consumptionMode;
        if (isset(self::$costCache[$cacheKey])) return self::$costCache[$cacheKey];

        $query = TechnicalSheet::where("parent_product_id", $product->id)->where("is_active", true);

        if ($consumptionMode === 'dine_in') {
            $query->whereIn('consumption_scope', [
                \App\Enums\ConsumptionChannelScope::ALL->value,
                \App\Enums\ConsumptionChannelScope::DINE_IN->value,
            ]);
        } elseif ($consumptionMode === 'takeaway') {
            $query->whereIn('consumption_scope', [
                \App\Enums\ConsumptionChannelScope::ALL->value,
                \App\Enums\ConsumptionChannelScope::TAKEAWAY->value,
            ]);
        }

        $sheetItems = $query->get();

        if ($sheetItems->isEmpty()) {
            $latestHistory = ProductCostHistory::where("product_id", $product->id)->orderByDesc("created_at")->orderByDesc("id")->first();
            $cost = $latestHistory ? (float) $latestHistory->unit_cost : (float) ($product->base_cost ?? 0);
            return self::$costCache[$cacheKey] = $cost;
        }

        $cost = 0;
        foreach ($sheetItems as $item) {
            $child = Product::find($item->ingredient_product_id);
            if ($child) {
                $cost += self::calculate($child, $consumptionMode) * (float) $item->quantity;
            }
        }

        $yield = max((float) ($product->recipe_yield ?? 1), 1);
        return self::$costCache[$cacheKey] = ($cost / $yield);
    }
}
