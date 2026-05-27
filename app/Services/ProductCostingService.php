<?php

namespace App\Services;

use App\Models\Product;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;

class ProductCostingService
{
    public static function getMarginData(Product $product): array
    {
        $sellPrice =
            (float) ($product->dine_in_price ??
                ($product->delivery_price ?? 0));

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

        // 🚀 INJEÇÃO DO CUSTO PREDITIVO (BI)
        $predictiveModifiersCost = self::getPredictiveModifiersCost($product);
        $baseCost += $predictiveModifiersCost;

        // 3. Impostos Individuais
        $settings = CompanySetting::first();
        $simplesRate = (float) ($settings->simples_rate ?? 0);

        // 🚀 CORREÇÃO CONTÁBIL: No Simples/MEI o ICMS é cobrado por dentro da guia.
        $icmsRate = 0;
        $icmsAmount = 0;

        $simplesAmount = $sellPrice * ($simplesRate / 100);

        // 4. Taxas de Cartão
        $avgFeeResult = DB::table("bank_accounts")
            ->where("is_active", true)
            ->selectRaw("AVG((credit_fee + debit_fee) / 2) as avg_fee")
            ->first();

        $cardFeePercentage = (float) ($avgFeeResult->avg_fee ?? 0);
        $cardFeeAmount = $sellPrice * ($cardFeePercentage / 100);

        // 🚀 A SOMA REAL DOS VARIÁVEIS
        $totalVariables = $simplesAmount + $icmsAmount + $cardFeeAmount;

        // 5. Margem de Contribuição
        $contributionMargin = $sellPrice - $baseCost - $totalVariables;
        $marginPercentage =
            $sellPrice > 0 ? ($contributionMargin / $sellPrice) * 100 : 0;

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
            "simples_display" =>
                $settings->tax_regime === "mei"
                    ? "ISENTO"
                    : $fmt($simplesAmount),
            "icms_display" => $product->is_monofasico
                ? "ISENTO"
                : $fmt($icmsAmount),
            "card_fee_amount" => $cardFeeAmount,
            "contribution_margin" => $contributionMargin,
            "marginPercentage" => $marginPercentage,
            "margin_percentage" => $marginPercentage, // Alias de segurança
            "simples_rate" => $simplesRate,
            "icms_rate" => $icmsRate,
            "card_fee_percentage" => $cardFeePercentage,
        ];
    }

    /**
     * Calcula o custo de forma recursiva, filtrando pelo tipo de pedido ($orderType)
     */
    public static function calculateBaseCost(
        Product $product,
        string $orderType = "all",
    ): float {
        $sheetItems = \App\Models\TechnicalSheet::where(
            "parent_product_id",
            $product->id,
        )->get();

        if ($sheetItems->isEmpty()) {
            // 🚀 A MÁGICA AQUI: O Histórico de Compras é a Fonte da Verdade e sobrepõe o campo manual!
            $latestHistory = \App\Models\ProductCostHistory::where(
                "product_id",
                $product->id,
            )
                ->latest("id")
                ->first();

            if ($latestHistory) {
                // Se o produto foi comprado, ignoramos o manual e usamos o da nota!
                return (float) $latestHistory->unit_cost;
            }

            // Se nunca foi comprado, usamos o manual inicial
            return (float) ($product->base_cost ?? 0);
        }

        $cost = 0;

        foreach ($sheetItems as $item) {
            $channelsArray = is_array($item->order_type)
                ? $item->order_type
                : ["table", "counter", "delivery"];

            if ($orderType === "all" || in_array($orderType, $channelsArray)) {
                $child = Product::find($item->ingredient_product_id);
                if ($child) {
                    $childCost = self::calculateBaseCost($child, $orderType);
                    $cost += $childCost * (float) $item->quantity;
                }
            }
        }

        $yield = (float) ($product->recipe_yield ?? 1);
        if ($yield <= 0) {
            $yield = 1;
        }

        return $cost / $yield;
    }

    public static function getPredictiveModifiersCost(Product $product): float
    {
        $totalPredictiveCost = 0;
        $modifierGroups = $product
            ->modifiers()
            ->where("type", "ingredient")
            ->get();

        foreach ($modifierGroups as $group) {
            $options = $group
                ->options()
                ->whereNotNull("linked_product_id")
                ->get();
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
}
