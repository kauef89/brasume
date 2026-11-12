<?php
namespace App\Services\Catalog\Costing;

use App\Enums\ModifierType;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Services\Catalog\ProductCostingService;
use Illuminate\Support\Facades\DB;

class MarginCalculator
{
    public static function getMarginData(Product $product): array
    {
        $costDineIn   = ProductCostingService::calculateBaseCost($product, "dine_in");
        $costTakeaway = ProductCostingService::calculateBaseCost($product, "takeaway");

        $dineInPrice   = (float) ($product->dine_in_price ?? 0);
        $deliveryPrice = (float) ($product->delivery_price ?? 0);

        $sellPrice  = $dineInPrice > 0 ? $dineInPrice : $deliveryPrice;
        $primaryCmv = $dineInPrice > 0 ? $costDineIn : $costTakeaway;

        $predictiveCost = ProductCostingService::getPredictiveModifiersCost($product);
        $baseCost       = $primaryCmv + $predictiveCost;

        $settings    = CompanySetting::getCached();
        $simplesRate = (float) ($settings?->simples_rate ?? 0);
        $effectiveSimplesRate = $product->is_monofasico ? $simplesRate * (1 - 0.34) : $simplesRate;

        $simplesAmount = $sellPrice * ($effectiveSimplesRate / 100);

        $avgFeeResult = DB::table("bank_accounts")
            ->where("is_active", true)
            ->where(fn ($q) => $q->where('credit_fee', '>', 0)->orWhere('debit_fee', '>', 0))
            ->selectRaw("AVG((credit_fee + debit_fee) / 2) as avg_fee")->first();

        $cardFeePercentage = (float) ($avgFeeResult->avg_fee ?? 0);
        $cardFeeAmount     = $sellPrice * ($cardFeePercentage / 100);
        $totalVariables    = $simplesAmount + $cardFeeAmount;

        $contributionMargin = $sellPrice - $baseCost - $totalVariables;
        $marginPercentage   = $sellPrice > 0 ? ($contributionMargin / $sellPrice) * 100 : 0;

        $fmt = fn ($val) => 'R$ ' . number_format($val, 2, ",", ".");

        return [
            "sell_price"          => $sellPrice,
            "base_cost"           => $baseCost,
            "cost_dine_in"        => $costDineIn,
            "cost_takeaway"       => $costTakeaway,
            "cost_table"          => $costDineIn,
            "cost_delivery"       => $costTakeaway,
            "predictive_cost"     => $predictiveCost,
            "total_variables"     => $totalVariables,
            "simples_amount"      => $simplesAmount,
            "icms_amount"         => 0,
            "simples_display"     => ($settings?->tax_regime === "mei") ? "ISENTO" : $fmt($simplesAmount),
            "icms_display"        => $product->is_monofasico ? "MONOFÁSICO" : "ISENTO",
            "card_fee_amount"     => $cardFeeAmount,
            "contribution_margin" => $contributionMargin,
            "marginPercentage"    => $marginPercentage,
            "margin_percentage"   => $marginPercentage,
            "simples_rate"        => $simplesRate,
            "icms_rate"           => 0,
            "card_fee_percentage" => $cardFeePercentage,
        ];
    }

    public static function simulatePricing(Product $product, array $selectedAddons, ?string $activeMode, ?float $customValue): array
    {
        $marginData = self::getMarginData($product);
        $modifierGroups = $product->modifiers()->whereIn('type', [ModifierType::OPTION->value, ModifierType::ADD_ON->value])->with('options')->get();

        $predictiveCostTotal = ProductCostingService::getPredictiveModifiersCost($product);
        $simulatedCost = $marginData['base_cost'] - $predictiveCostTotal;
        $addonSellPriceTotal = 0;

        foreach ($modifierGroups as $group) {
            $isProportional = ($group->portion_distribution_mode ?? 'additive') === 'proportional_split';
            $selectedInGroup = [];
            $extraPriceSum = 0.0;

            foreach ($group->options as $option) {
                if (in_array($option->id, $selectedAddons)) {
                    $selectedInGroup[] = $option;
                    $extraPriceSum += (float) $option->price;
                }
            }

            if (!empty($selectedInGroup)) {
                $propOptions = [];
                $fixedOptions = [];
                foreach ($selectedInGroup as $opt) {
                    if (($opt->portion_behavior ?? 'proportional') === 'fixed') {
                        $fixedOptions[] = $opt;
                    } else {
                        $propOptions[] = $opt;
                    }
                }

                $kProp = count($propOptions);
                $propFactor = ($isProportional && $kProp > 1) ? ((1.0 + (0.15 * ($kProp - 1))) / $kProp) : 1.0;

                $groupCost = 0.0;
                foreach ($fixedOptions as $option) {
                    if ($option->linked_product_id) {
                        $linkedInsumo = Product::find($option->linked_product_id);
                        if ($linkedInsumo) {
                            $optCost = ProductCostingService::calculateBaseCost($linkedInsumo) * (float) ($option->linked_qty ?? 1);
                            $groupCost += $optCost;
                        }
                    }
                }
                foreach ($propOptions as $option) {
                    if ($option->linked_product_id) {
                        $linkedInsumo = Product::find($option->linked_product_id);
                        if ($linkedInsumo) {
                            $optCost = ProductCostingService::calculateBaseCost($linkedInsumo) * (float) ($option->linked_qty ?? 1);
                            $groupCost += ($optCost * $propFactor);
                        }
                    }
                }

                $kTotal = count($selectedInGroup);
                $maxFree = $group->max_free > 0 ? (int) $group->max_free : 0;
                $extraCount = max(0, $kTotal - $maxFree);
                $incrementCharge = $extraCount * (float) ($group->increment ?? 0);

                $addonSellPriceTotal += ($extraPriceSum + $incrementCharge);
                $simulatedCost += $groupCost;
            } else {
                $groupTypeVal = $group->type instanceof ModifierType ? $group->type->value : ($group->type instanceof \BackedEnum ? $group->type->value : (string)$group->type);
                if ($groupTypeVal === ModifierType::OPTION->value) {
                    $groupExpectedCost = 0;
                    $totalWeight = 0;
                    foreach ($group->options as $opt) {
                        $optCost = 0;
                        if ($opt->linked_product_id) {
                            $linked = Product::find($opt->linked_product_id);
                            if ($linked) {
                                $optCost = ProductCostingService::calculateBaseCost($linked) * (float) ($opt->linked_qty ?? 1);
                            }
                        }
                        $weight = max((float) ($opt->historical_weight ?? 1), 0.001);
                        $groupExpectedCost += ($optCost * $weight);
                        $totalWeight += $weight;
                    }
                    if ($totalWeight > 0) {
                        $avgCost = $groupExpectedCost / $totalWeight;
                        if ($isProportional) {
                            $maxFree = $group->max_free > 0 ? (int) $group->max_free : 1;
                            $simulatedCost += $avgCost * (1.0 + (0.15 * max(0, $maxFree - 1)));
                        } else {
                            $simulatedCost += $avgCost * ($group->max_free > 0 ? $group->max_free : 1);
                        }
                    }
                }
            }
        }

        $totalTaxRate = ($marginData['simples_rate'] ?? 0) + ($marginData['card_fee_percentage'] ?? 0);
        $simulatedSellPrice = $marginData['sell_price'];

        if ($activeMode === null) {
            $simulatedSellPrice += $addonSellPriceTotal;
        } elseif ($activeMode === 'margin' && $customValue !== null) {
            $divisor = 1 - (($totalTaxRate + $customValue) / 100);
            $simulatedSellPrice = $divisor > 0 ? ($simulatedCost / $divisor) : 0;
        } elseif ($activeMode === 'price' && $customValue !== null) {
            $simulatedSellPrice = $customValue;
        }

        $simplesAmount = $simulatedSellPrice * (($marginData['simples_rate'] ?? 0) / 100);
        $cardFeeAmount = $simulatedSellPrice * (($marginData['card_fee_percentage'] ?? 0) / 100);
        $totalVariableCosts = $simplesAmount + $cardFeeAmount;

        $netProfit = $simulatedSellPrice - $simulatedCost - $totalVariableCosts;
        $marginPercentage = $simulatedSellPrice > 0 ? ($netProfit / $simulatedSellPrice) * 100 : 0;

        return [
            'sell_price'           => $simulatedSellPrice,
            'cost'                 => $simulatedCost,
            'net_profit'           => $netProfit,
            'contribution_margin'  => $netProfit,
            'margin_percentage'    => $marginPercentage,
            'simples_amount'       => $simplesAmount,
            'icms_amount'          => 0,
            'card_fee_amount'      => $cardFeeAmount,
            'total_variable_costs' => $totalVariableCosts,
        ];
    }
}