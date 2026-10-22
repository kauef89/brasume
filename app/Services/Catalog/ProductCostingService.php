<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;

class ProductCostingService
{
    private static array $costCache = [];

    public static function clearCache(): void
    {
        self::$costCache = [];
    }

    public static function getMarginData(Product $product): array
    {
        // 1. CMV por Modo de Consumo Real
        $costDineIn   = self::calculateBaseCost($product, "dine_in");
        $costTakeaway = self::calculateBaseCost($product, "takeaway");

        // Preços Praticados
        $dineInPrice   = (float) ($product->dine_in_price ?? 0);
        $deliveryPrice = (float) ($product->delivery_price ?? 0);

        // Preço e Custo primário a exibir no topo
        $sellPrice = $dineInPrice > 0 ? $dineInPrice : $deliveryPrice;
        $primaryCmv = $dineInPrice > 0 ? $costDineIn : $costTakeaway;

        // Injeção do Custo Preditivo de Ingredientes/Modificadores
        $predictiveCost = self::getPredictiveModifiersCost($product);
        $baseCost = $primaryCmv + $predictiveCost;

        // 2. Parâmetros Fiscais (Simples Nacional)
        $settings = CompanySetting::getCached();
        $simplesRate = (float) ($settings?->simples_rate ?? 0);

        $effectiveSimplesRate = $simplesRate;
        if ($product->is_monofasico) {
            $effectiveSimplesRate = $simplesRate * (1 - 0.34);
        }

        $simplesAmount = $sellPrice * ($effectiveSimplesRate / 100);

        // 3. Taxas de Cartão
        $avgFeeResult = DB::table("bank_accounts")
            ->where("is_active", true)
            ->where(function ($q) {
                $q->where('credit_fee', '>', 0)->orWhere('debit_fee', '>', 0);
            })
            ->selectRaw("AVG((credit_fee + debit_fee) / 2) as avg_fee")
            ->first();

        $cardFeePercentage = (float) ($avgFeeResult->avg_fee ?? 0);
        $cardFeeAmount = $sellPrice * ($cardFeePercentage / 100);

        $totalVariables = $simplesAmount + $cardFeeAmount;

        // 4. Margem de Contribuição
        $contributionMargin = $sellPrice - $baseCost - $totalVariables;
        $marginPercentage   = $sellPrice > 0 ? ($contributionMargin / $sellPrice) * 100 : 0;

        $fmt = fn($val) => 'R$ ' . number_format($val, 2, ",", ".");

        return [
            "sell_price"          => $sellPrice,
            "base_cost"           => $baseCost,
            // Sub-métricas para os cartões
            "cost_dine_in"        => $costDineIn,
            "cost_takeaway"       => $costTakeaway,
            "cost_table"          => $costDineIn,   // alias para compatibilidade
            "cost_delivery"       => $costTakeaway, // alias para compatibilidade
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

    /**
     * Calcula o custo base recursivo filtrando pelo Modo de Consumo:
     * 'all' (geral), 'dine_in' (Consumo no Local) ou 'takeaway' (Para Viagem)
     */
    public static function calculateBaseCost(Product $product, string $consumptionMode = "all"): float
    {
        $cacheKey = $product->id . '_' . $consumptionMode;

        if (isset(self::$costCache[$cacheKey])) {
            return self::$costCache[$cacheKey];
        }

        $query = \App\Models\TechnicalSheet::where("parent_product_id", $product->id)
            ->where("is_active", true);

        // Filtro estrito pelo modo de consumo real
        if ($consumptionMode === 'dine_in') {
            $query->where('is_dine_in', true);
        } elseif ($consumptionMode === 'takeaway') {
            $query->where('is_takeaway', true);
        }

        $sheetItems = $query->get();

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
            $child = Product::find($item->ingredient_product_id);
            if ($child) {
                $childCost = self::calculateBaseCost($child, $consumptionMode);
                $cost += $childCost * (float) $item->quantity;
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
            $options = $group->options()->get();
            if ($options->isEmpty()) continue;

            $groupExpectedCost = 0;
            $totalWeight = 0;

            // 1. Identifica se o grupo já possui histórico de vendas
            $groupHasHistory = $options->sum('historical_weight') > 0;

            foreach ($options as $option) {
                // Opções com acréscimo pago à parte não compõem o custo do prato base
                if ((float) $option->price > 0 && ($group->max_free ?? 0) <= 0) {
                    continue;
                }

                $optionCost = 0;
                if (!empty($option->linked_product_id)) {
                    $linkedInsumo = Product::find($option->linked_product_id);
                    if ($linkedInsumo) {
                        $actualCost = self::calculateBaseCost($linkedInsumo);
                        $optionCost = $actualCost * (float) ($option->linked_qty ?? 1);
                    }
                }

                // Se houver histórico, usa o peso real da base de dados.
                // Se o item for novo (peso 0), atribui peso 0.1 para compor a cauda longa.
                // Se ninguém do grupo tiver histórico, usa peso 1 para todos (média simples).
                if ($groupHasHistory) {
                    $weight = (float) $option->historical_weight > 0 ? (float) $option->historical_weight : 0.1;
                } else {
                    $weight = 1.0;
                }

                $groupExpectedCost += $optionCost * $weight;
                $totalWeight += $weight;
            }

            if ($totalWeight > 0) {
                $averageOptionCost = $groupExpectedCost / $totalWeight;
                $includedQty = $group->max_free > 0 ? (int) $group->max_free : 1;
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
                    $addonSellPriceTotal += (float) $option->price;

                    if ($option->linked_product_id) {
                        $linkedInsumo = Product::find($option->linked_product_id);
                        if ($linkedInsumo) {
                            $actualCost = self::calculateBaseCost($linkedInsumo);
                            $groupSimulatedCost += $actualCost * (float) ($option->linked_qty ?? 1);
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
                        $optCost = 0;
                        if ($opt->linked_product_id) {
                            $linked = Product::find($opt->linked_product_id);
                            if ($linked) {
                                $optCost = self::calculateBaseCost($linked) * (float) ($opt->linked_qty ?? 1);
                            }
                        }
                        $weight = max((float) ($opt->historical_weight ?? 1), 0.001);
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
        $cardFeePercentage = $marginData['card_fee_percentage'] ?? 0;
        $totalTaxRate = $simplesRate + $cardFeePercentage;

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
        $cardFeeAmount = $simulatedSellPrice * ($cardFeePercentage / 100);
        $totalVariableCosts = $simplesAmount + $cardFeeAmount;

        $netProfit = $simulatedSellPrice - $simulatedCost - $totalVariableCosts;
        $marginPercentage = $simulatedSellPrice > 0 ? ($netProfit / $simulatedSellPrice) * 100 : 0;

        return [
            'sell_price'          => $simulatedSellPrice,
            'cost'                => $simulatedCost,
            'net_profit'          => $netProfit,
            'contribution_margin' => $netProfit,
            'margin_percentage'   => $marginPercentage,
            'simples_amount'      => $simplesAmount,
            'icms_amount'         => 0,
            'card_fee_amount'     => $cardFeeAmount,
            'total_variable_costs'=> $totalVariableCosts,
        ];
    }

    public static function recordPriceSnapshot(Product $product, string $trigger = 'price_change'): void
    {
        self::clearCache();
        $marginData = self::getMarginData($product);

        $modes = [
            'dine_in' => [
                'price' => (float) ($product->dine_in_price ?? 0),
                'cost'  => (float) $marginData['cost_dine_in'] + (float) $marginData['predictive_cost'],
            ],
            'takeaway' => [
                'price' => (float) ($product->delivery_price ?? ($product->dine_in_price ?? 0)),
                'cost'  => (float) $marginData['cost_takeaway'] + (float) $marginData['predictive_cost'],
            ],
        ];

        foreach ($modes as $channelName => $ch) {
            if ($ch['price'] <= 0) continue;

            $currentLog = \App\Models\ProductPriceLog::where('product_id', $product->id)
                ->where('channel', $channelName)
                ->whereNull('ended_at')
                ->latest('id')
                ->first();

            $cost = round($ch['cost'], 4);
            $simples = $ch['price'] * (($marginData['simples_rate'] ?? 0) / 100);
            $card = $ch['price'] * (($marginData['card_fee_percentage'] ?? 0) / 100);
            $variables = $simples + $card;
            $margin = $ch['price'] - $cost - $variables;
            $marginPct = $ch['price'] > 0 ? round(($margin / $ch['price']) * 100, 2) : 0;

            if ($currentLog &&
                abs((float) $currentLog->price - $ch['price']) < 0.01 &&
                abs((float) $currentLog->cost - $cost) < 0.01) {
                continue;
            }

            if ($currentLog) {
                $currentLog->update(['ended_at' => now()]);
            }

            \App\Models\ProductPriceLog::create([
                'product_id'          => $product->id,
                'channel'             => $channelName,
                'price'               => $ch['price'],
                'cost'                => $cost,
                'total_variables'     => $variables,
                'contribution_margin' => $margin,
                'margin_percentage'   => $marginPct,
                'started_at'          => now(),
                'ended_at'            => null,
                'trigger_reason'      => $trigger,
            ]);
        }
    }

    /**
     * Gera o raio-x de variação de CMV, oscilação de margem e identificação de ofensores.
     */
    public static function getCostVarianceAnalysis(Product $product, int $days = 30): array
    {
        $currentMargin = self::getMarginData($product);
        $currentCmv = (float) $currentMargin['base_cost'];
        $currentMarginPct = (float) $currentMargin['margin_percentage'];

        // 1. Busca condição anterior no histórico (ProductPriceLog)
        $previousLog = \App\Models\ProductPriceLog::where('product_id', $product->id)
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', now()->subDays($days))
            ->orderBy('id', 'desc')
            ->first();

        // Se não houver log recente dentro dos X dias, busca o último encerrado como fallback
        if (!$previousLog) {
            $previousLog = \App\Models\ProductPriceLog::where('product_id', $product->id)
                ->whereNotNull('ended_at')
                ->orderBy('id', 'desc')
                ->first();
        }

        $prevCmv = $previousLog ? (float) $previousLog->cost : $currentCmv;
        $prevMarginPct = $previousLog ? (float) $previousLog->margin_percentage : $currentMarginPct;

        $cmvDiffNominal = $currentCmv - $prevCmv;
        $cmvDiffPct = $prevCmv > 0 ? (($currentCmv - $prevCmv) / $prevCmv) * 100 : 0;
        $marginDiffPp = $currentMarginPct - $prevMarginPct;

        // 2. Análise Itemizada da Ficha Técnica (BOM Explosion & Detecção de Ofensores)
        $sheetItems = \App\Models\TechnicalSheet::where('parent_product_id', $product->id)
            ->where('is_active', true)
            ->with(['ingredient.costHistories'])
            ->get();

        $ingredientsAnalysis = [];
        $alerts = [];
        $totalRecipeCost = 0;

        foreach ($sheetItems as $item) {
            $ing = $item->ingredient;
            if (!$ing) continue;

            $unitCost = self::calculateBaseCost($ing);
            $totalCost = $unitCost * (float) $item->quantity;
            $totalRecipeCost += $totalCost;

            // Histórico de compras para pegar variação da última NF-e vs penúltima
            $recentPurchases = $ing->costHistories->sortByDesc('id')->take(2)->values();
            $latestPurchaseCost = $recentPurchases->get(0)?->unit_cost;
            $previousPurchaseCost = $recentPurchases->get(1)?->unit_cost;

            $itemInflationPct = 0;
            if ($latestPurchaseCost && $previousPurchaseCost && (float) $previousPurchaseCost > 0) {
                $itemInflationPct = (((float) $latestPurchaseCost - (float) $previousPurchaseCost) / (float) $previousPurchaseCost) * 100;
            }

            $ingredientsAnalysis[] = [
                'id'            => $ing->id,
                'name'          => $ing->name,
                'quantity'      => (float) $item->quantity,
                'uom'           => $ing->unit_of_measure,
                'unit_cost'     => $unitCost,
                'total_cost'    => $totalCost,
                'inflation_pct' => $itemInflationPct,
            ];
        }

        // Calcula share no CMV e classifica alertas/ofensores
        foreach ($ingredientsAnalysis as &$ingData) {
            $sharePct = $totalRecipeCost > 0 ? ($ingData['total_cost'] / $totalRecipeCost) * 100 : 0;
            $ingData['share_pct'] = $sharePct;

            // Critérios de Alerta: inflação > 5% na última compra OU concentra > 35% do custo do prato
            if ($ingData['inflation_pct'] >= 5) {
                $alerts[] = [
                    'name'     => $ingData['name'],
                    'type'     => 'inflation',
                    'badge'    => '+' . number_format($ingData['inflation_pct'], 1, ',', '.') . '% na NF-e',
                    'detail'   => 'Alta de preço detectada nas últimas notas fiscais.',
                    'severity' => $ingData['inflation_pct'] >= 15 ? 'danger' : 'warning',
                ];
            } elseif ($sharePct >= 35) {
                $alerts[] = [
                    'name'     => $ingData['name'],
                    'type'     => 'concentration',
                    'badge'    => number_format($sharePct, 1, ',', '.') . '% do CMV',
                    'detail'   => 'Insumo âncora: grande sensibilidade na margem.',
                    'severity' => 'info',
                ];
            }
        }
        unset($ingData);

        // Ordena ingredientes pelo maior custo total
        usort($ingredientsAnalysis, fn($a, $b) => $b['total_cost'] <=> $a['total_cost']);

        return [
            'has_history'       => $previousLog !== null,
            'period_days'       => $days,
            'current_cmv'       => $currentCmv,
            'cmv_diff_nominal'  => $cmvDiffNominal,
            'cmv_diff_pct'      => $cmvDiffPct,
            'cmv_status'        => $cmvDiffPct > 0.5 ? 'rising' : ($cmvDiffPct < -0.5 ? 'falling' : 'stable'),
            'current_margin'    => $currentMarginPct,
            'margin_diff_pp'    => $marginDiffPp,
            'margin_status'     => $marginDiffPp >= 0 ? 'gaining' : 'dropping',
            'top_ingredients'   => array_slice($ingredientsAnalysis, 0, 5),
            'alerts'            => $alerts,
        ];
    }
}
