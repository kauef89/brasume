<?php
namespace App\Services\Catalog\Costing;

use App\Enums\ModifierType;
use App\Models\Product;
use App\Services\Catalog\ProductCostingService;

class PredictiveCostCalculator
{
    public static function calculate(Product $product): float
    {
        $totalPredictiveCost = 0;
        $modifierGroups = $product->modifiers()->where('type', ModifierType::OPTION->value)->get();

        foreach ($modifierGroups as $group) {
            $options = $group->options()->where('is_active', true)->get();
            if ($options->isEmpty()) continue;

            $groupExpectedCost = 0;
            $totalWeight = 0;
            $groupHasHistory = $options->sum('historical_weight') > 0;

            foreach ($options as $option) {
                if ((float) $option->price > 0 && ($group->max_free ?? 0) <= 0) continue;

                $optionCost = 0;
                if (!empty($option->linked_product_id)) {
                    $linkedInsumo = Product::find($option->linked_product_id);
                    if ($linkedInsumo) {
                        $actualCost = ProductCostingService::calculateBaseCost($linkedInsumo);
                        $optionCost = $actualCost * (float) ($option->linked_qty ?? 1);
                    }
                }

                $weight = $groupHasHistory ? ((float) $option->historical_weight > 0 ? (float) $option->historical_weight : 0.1) : 1.0;
                $groupExpectedCost += $optionCost * $weight;
                $totalWeight += $weight;
            }

            if ($totalWeight > 0) {
                $averageOptionCost = $groupExpectedCost / $totalWeight;
                $isProportional = ($group->portion_distribution_mode ?? 'additive') === 'proportional_split';

                if ($isProportional) {
                    // No rateio proporcional, o cliente não recebe N porções cheias somadas.
                    // Ele recebe 1 porção base dividida com expansão gastronômica de 15% por opção extra.
                    $maxFree = $group->max_free > 0 ? (int) $group->max_free : max((int) ($group->min_selections ?? 1), 1);
                    $fillingExpansionFactor = 1.0 + (0.15 * max(0, $maxFree - 1));
                    $totalPredictiveCost += $averageOptionCost * $fillingExpansionFactor;
                } else {
                    $includedQty = $group->max_free > 0 ? (int) $group->max_free : max((int) ($group->min_selections ?? 1), 1);
                    $totalPredictiveCost += $averageOptionCost * $includedQty;
                }
            }
        }
        return $totalPredictiveCost;
    }
}
