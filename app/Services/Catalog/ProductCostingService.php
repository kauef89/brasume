<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;

class ProductCostingService
{
    // 🚀 CACHE EM MEMÓRIA: Evita consultas recursivas pesadas
    private static array $costCache = [];

    /**
     * Limpa o cache estático em memória (essencial para testes e workers).
     */
    public static function clearCache(): void
    {
        self::$costCache = [];
    }

    /**
     * @return array<string,mixed>
     */
    public static function getMarginData(Product $product): array
    {
        $sellPrice = (float) ($product->dine_in_price ?? ($product->delivery_price ?? 0));

        // 1. Custos de Insumos (CMV)
        $costTable = self::calculateBaseCost($product, "table");
        $costCounter = self::calculateBaseCost($product, "counter");
        $costDelivery = self::calculateBaseCost($product, "delivery");

        // 2. Média Ponderada
        $activeChannels = 0;
        $sumCost = 0;
        if ($product->show_on_menu) {
            $sumCost += $costTable + $costCounter;
            $activeChannels += 2;
        }
        if ($product->show_on_delivery) {
            $sumCost += $costDelivery;
            $activeChannels += 1;
        }
        if ($activeChannels === 0) {
            $sumCost = $costTable + $costCounter + $costDelivery;
            $activeChannels = 3;
        }

        $baseCost = $sumCost / $activeChannels;

        // INJEÇÃO DO CUSTO PREDITIVO (BI)
        $predictiveModifiersCost = self::getPredictiveModifiersCost($product);
        $baseCost += $predictiveModifiersCost;

        // 3. Impostos Individuais com Proteção Null-Safe
        $settings = CompanySetting::getCached();
        $simplesRate = (float) ($settings?->simples_rate ?? 0);

        $icmsRate = 0;
        $icmsAmount = 0;

        $effectiveSimplesRate = $simplesRate;
        if ($product->is_monofasico) {
            $effectiveSimplesRate = $simplesRate * (1 - 0.34);
        }

        $simplesAmount = $sellPrice * ($effectiveSimplesRate / 100);

        // 4. Taxas de Cartão
        $avgFeeResult = DB::table("bank_accounts")
            ->where("is_active", true)
            ->selectRaw("AVG((credit_fee + debit_fee) / 2) as avg_fee")
            ->first();

        $cardFeePercentage = (float) ($avgFeeResult->avg_fee ?? 0);
        $cardFeeAmount = $sellPrice * ($cardFeePercentage / 100);

        // A SOMA REAL DOS VARIÁVEIS
        $totalVariables = $simplesAmount + $icmsAmount + $cardFeeAmount;

        // 5. Margem de Contribuição
        $contributionMargin = $sellPrice - $baseCost - $totalVariables;
        $marginPercentage = $sellPrice > 0 ? ($contributionMargin / $sellPrice) * 100 : 0;

        $fmt = fn($val) => 'R$ ' . number_format($val, 2, ",", ".");

        return [
            "sell_price" => $sellPrice,
            "base_cost" => $baseCost,
            "cost_table" => $costTable,
            "cost_counter" => $costCounter,
            "cost_delivery" => $costDelivery,
            "total_variables" => $totalVariables,
            "simples_amount" => $simplesAmount,
            "icms_amount" => $icmsAmount,
            "simples_display" => ($settings?->tax_regime === "mei") ? "ISENTO" : $fmt($simplesAmount),
            "icms_display" => $product->is_monofasico ? "ISENTO" : $fmt($icmsAmount),
            "card_fee_amount" => $cardFeeAmount,
            "contribution_margin" => $contributionMargin,
            "marginPercentage" => $marginPercentage,
            "margin_percentage" => $marginPercentage,
            "simples_rate" => $simplesRate,
            "icms_rate" => $icmsRate,
            "card_fee_percentage" => $cardFeePercentage,
        ];
    }

    /**
     * Calcula o custo de forma recursiva, filtrando pelo tipo de pedido ($orderType)
     * e considerando APENAS insumos com is_active = true.
     */
    public static function calculateBaseCost(Product $product, string $orderType = "all"): float
    {
        $cacheKey = $product->id . '_' . $orderType;

        if (isset(self::$costCache[$cacheKey])) {
            return self::$costCache[$cacheKey];
        }

        // 🛡️ Filtra somente insumos ativos na Ficha Técnica
        $sheetItems = \App\Models\TechnicalSheet::where("parent_product_id", $product->id)
            ->where("is_active", true)
            ->get();

        if ($sheetItems->isEmpty()) {
            $latestHistory = \App\Models\ProductCostHistory::where("product_id", $product->id)
                ->latest("id")
                ->first();

            if ($latestHistory) {
                $cost = (float) $latestHistory->unit_cost;
                self::$costCache[$cacheKey] = $cost;
                return $cost;
            }

            $cost = (float) ($product->base_cost ?? 0);
            self::$costCache[$cacheKey] = $cost;
            return $cost;
        }

        $cost = 0;

        foreach ($sheetItems as $item) {
            $channelsArray = is_array($item->order_type) ? $item->order_type : ["table", "counter", "delivery"];

            if ($orderType === "all" || in_array($orderType, $channelsArray)) {
                $child = Product::find($item->ingredient_product_id);
                if ($child) {
                    $childCost = self::calculateBaseCost($child, $orderType);
                    $cost += $childCost * (float) $item->quantity;
                }
            }
        }

        $yield = max((float) ($product->recipe_yield ?? 1), 1);
        $finalCost = $cost / $yield;

        self::$costCache[$cacheKey] = $finalCost;

        return $finalCost;
    }

    public static function getPredictiveModifiersCost(Product $product): float
    {
        $totalPredictiveCost = 0;
        $modifierGroups = $product->modifiers()->where("type", "ingredient")->get();

        foreach ($modifierGroups as $group) {
            $options = $group->options()->whereNotNull("linked_product_id")->get();
            if ($options->isEmpty()) {
                continue;
            }

            $groupExpectedCost = 0;
            $totalWeight = 0;

            foreach ($options as $option) {
                $linkedInsumo = Product::find($option->linked_product_id);
                if (!$linkedInsumo) {
                    continue;
                }

                $actualCost = self::calculateBaseCost($linkedInsumo);
                $optionCost = $actualCost * ($option->linked_qty ?? 0);
                $weight = $option->historical_weight ?? 1;

                $groupExpectedCost += $optionCost * $weight;
                $totalWeight += $weight;
            }

            if ($totalWeight > 0) {
                $averageOptionCost = $groupExpectedCost / $totalWeight;
                $includedQty = $group->max_free > 0 ? $group->max_free : 1;
                $totalPredictiveCost += $averageOptionCost * $includedQty;
            }
        }

        return $totalPredictiveCost;
    }

    public static function simulatePricing(Product $product, array $selectedAddons, ?string $activeMode, ?float $customValue): array
    {
        $marginData = self::getMarginData($product);
        $modifierGroups = $product->modifiers()->whereIn('type', ['add_on', 'ingredient'])->with('options')->get();

        $predictiveCostTotal = self::getPredictiveModifiersCost($product);
        $simulatedCost = $marginData['base_cost'] - $predictiveCostTotal;
        $addonSellPriceTotal = 0;

        foreach ($modifierGroups as $group) {
            $groupIsSimulated = false;
            $groupSimulatedCost = 0;

            foreach ($group->options as $option) {
                if (in_array($option->id, $selectedAddons)) {
                    $groupIsSimulated = true;
                    $addonSellPriceTotal += $option->price;

                    if ($option->linked_product_id) {
                        $linkedInsumo = \App\Models\Product::find($option->linked_product_id);
                        if ($linkedInsumo) {
                            $actualCost = self::calculateBaseCost($linkedInsumo);
                            $groupSimulatedCost += $actualCost * ($option->linked_qty ?? 0);
                        }
                    }
                }
            }

            if ($groupIsSimulated) {
                $simulatedCost += $groupSimulatedCost;
            } else {
                if ($group->type === 'ingredient') {
                    $groupExpectedCost = 0;
                    $totalWeight = 0;
                    foreach ($group->options as $opt) {
                        if (!$opt->linked_product_id) continue;
                        $linked = \App\Models\Product::find($opt->linked_product_id);
                        if (!$linked) continue;

                        $optCost = self::calculateBaseCost($linked) * ($opt->linked_qty ?? 0);
                        $weight = $opt->historical_weight ?? 1;

                        $groupExpectedCost += ($optCost * $weight);
                        $totalWeight += $weight;
                    }
                    if ($totalWeight > 0) {
                        $simulatedCost += ($groupExpectedCost / $totalWeight) * ($group->max_free > 0 ? $group->max_free : 1);
                    }
                }
            }
        }

        $simplesRate = $marginData['simples_rate'] ?? 0;
        $icmsRate = ($product->is_monofasico ?? false) ? 0 : ($marginData['icms_rate'] ?? 0);
        $cardFeePercentage = $marginData['card_fee_percentage'] ?? 0;
        $totalTaxRate = $simplesRate + $icmsRate + $cardFeePercentage;

        $simulatedSellPrice = $marginData['sell_price'];

        if ($activeMode === null) {
            $simulatedSellPrice += $addonSellPriceTotal;
        } elseif ($activeMode === 'margin' && $customValue !== null) {
            $divisor = 1 - (($totalTaxRate + $customValue) / 100);
            $simulatedSellPrice = $divisor > 0 ? ($simulatedCost / $divisor) : 0;
        } elseif ($activeMode === 'price' && $customValue !== null) {
            $simulatedSellPrice = $customValue;
        }

        $simplesAmount = $simulatedSellPrice * ($simplesRate / 100);
        $icmsAmount = $simulatedSellPrice * ($icmsRate / 100);
        $cardFeeAmount = $simulatedSellPrice * ($cardFeePercentage / 100);
        $totalVariableCosts = $simplesAmount + $icmsAmount + $cardFeeAmount;

        $netProfit = $simulatedSellPrice - $simulatedCost - $totalVariableCosts;
        $marginPercentage = $simulatedSellPrice > 0 ? ($netProfit / $simulatedSellPrice) * 100 : 0;

        return [
            'sell_price' => $simulatedSellPrice,
            'cost' => $simulatedCost,
            'net_profit' => $netProfit,
            'contribution_margin' => $netProfit,
            'margin_percentage' => $marginPercentage,
            'simples_amount' => $simplesAmount,
            'icms_amount' => $icmsAmount,
            'card_fee_amount' => $cardFeeAmount,
            'total_variable_costs' => $totalVariableCosts,
        ];
    }
}
