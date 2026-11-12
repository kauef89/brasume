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

            // Filtra apenas opções inclusas no preço base (price == 0) para o cálculo do CMV do produto base.
            // Opções especiais (price > 0) são custeadas pelo seu próprio adicional no PDV e no Simulador.
            $includedOptions = $options->filter(fn ($opt) => (float) ($opt->price ?? 0) <= 0);
            if ($includedOptions->isEmpty()) {
                $includedOptions = $options;
            }

            $groupHasHistory = $includedOptions->sum('historical_weight') > 0;
            $isProportional = ($group->portion_distribution_mode ?? 'additive') === 'proportional_split';

            $propWeight = 0;
            $propCostSum = 0;
            $fixedWeight = 0;
            $fixedCostSum = 0;
            $totalWeight = 0;

            foreach ($includedOptions as $option) {
                $optionCost = 0;
                if (!empty($option->linked_product_id)) {
                    $linkedInsumo = Product::find($option->linked_product_id);
                    if ($linkedInsumo) {
                        $actualCost = ProductCostingService::calculateBaseCost($linkedInsumo);
                        $optionCost = $actualCost * (float) ($option->linked_qty ?? 1);
                    }
                }

                $weight = $groupHasHistory ? ((float) $option->historical_weight > 0 ? (float) $option->historical_weight : 0.1) : 1.0;
                $totalWeight += $weight;

                if (($option->portion_behavior ?? 'proportional') === 'fixed') {
                    $fixedCostSum += ($optionCost * $weight);
                    $fixedWeight += $weight;
                } else {
                    $propCostSum += ($optionCost * $weight);
                    $propWeight += $weight;
                }
            }

            if ($totalWeight > 0) {
                $maxFree = $group->max_free > 0 ? (int) $group->max_free : max((int) ($group->min_selections ?? 1), 1);

                if ($isProportional) {
                    $expPropChoices = $maxFree * ($propWeight / $totalWeight);
                    $expFixedChoices = $maxFree * ($fixedWeight / $totalWeight);

                    $avgPropCost = $propWeight > 0 ? ($propCostSum / $propWeight) : 0;
                    $avgFixedCost = $fixedWeight > 0 ? ($fixedCostSum / $fixedWeight) : 0;

                    $propExpansion = $expPropChoices > 1 ? (1.0 + (0.15 * ($expPropChoices - 1))) : min(1.0, $expPropChoices);
                    $totalPredictiveCost += ($avgPropCost * $propExpansion) + ($avgFixedCost * $expFixedChoices);
                } else {
                    $averageOptionCost = ($propCostSum + $fixedCostSum) / $totalWeight;
                    $totalPredictiveCost += $averageOptionCost * $maxFree;
                }
            }
        }
        return $totalPredictiveCost;
    }
}