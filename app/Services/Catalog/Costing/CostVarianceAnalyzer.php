<?php
namespace App\Services\Catalog\Costing;

use App\Enums\ModifierType;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\TechnicalSheet;
use App\Services\Catalog\ProductCostingService;

class CostVarianceAnalyzer
{
    public static function recordSnapshot(Product $product, string $trigger = 'price_change'): void
    {
        ProductCostingService::clearCache();
        $marginData = ProductCostingService::getMarginData($product);

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

            $currentLog = ProductPriceLog::where('product_id', $product->id)->where('channel', $channelName)->whereNull('ended_at')->latest('id')->first();
            $cost = round($ch['cost'], 4);
            $variables = ($ch['price'] * (($marginData['simples_rate'] ?? 0) / 100)) + ($ch['price'] * (($marginData['card_fee_percentage'] ?? 0) / 100));
            $margin = $ch['price'] - $cost - $variables;
            $marginPct = $ch['price'] > 0 ? round(($margin / $ch['price']) * 100, 2) : 0;

            if ($currentLog && abs((float) $currentLog->price - $ch['price']) < 0.01 && abs((float) $currentLog->cost - $cost) < 0.01) continue;
            if ($currentLog) $currentLog->update(['ended_at' => now()]);

            ProductPriceLog::create([
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

    public static function analyze(Product $product, int $days = 30): array
    {
        $currentMargin = ProductCostingService::getMarginData($product);
        $currentCmv = (float) $currentMargin['base_cost'];
        $currentMarginPct = (float) $currentMargin['margin_percentage'];

        $previousLog = ProductPriceLog::where('product_id', $product->id)->whereNotNull('ended_at')->where('started_at', '>=', now()->subDays($days))->orderBy('id', 'desc')->first()
            ?? ProductPriceLog::where('product_id', $product->id)->whereNotNull('ended_at')->orderBy('id', 'desc')->first();

        $prevCmv = $previousLog ? (float) $previousLog->cost : $currentCmv;
        $prevMarginPct = $previousLog ? (float) $previousLog->margin_percentage : $currentMarginPct;

        $cmvDiffNominal = $currentCmv - $prevCmv;
        $cmvDiffPct = $prevCmv > 0 ? (($currentCmv - $prevCmv) / $prevCmv) * 100 : 0;

        $ingredientsAnalysis = [];
        $alerts = [];
        $totalRecipeCost = 0;

        $sheetItems = TechnicalSheet::where('parent_product_id', $product->id)->where('is_active', true)->with(['ingredient.costHistories'])->get();
        foreach ($sheetItems as $item) {
            $ing = $item->ingredient;
            if (!$ing) continue;

            $unitCost = ProductCostingService::calculateBaseCost($ing);
            $totalCost = $unitCost * (float) $item->quantity;
            $totalRecipeCost += $totalCost;

            $recent = $ing->costHistories->sortByDesc('id')->take(2)->values();
            $infPct = ($recent->get(0)?->unit_cost && $recent->get(1)?->unit_cost && (float) $recent->get(1)->unit_cost > 0)
                ? (((float) $recent->get(0)->unit_cost - (float) $recent->get(1)->unit_cost) / (float) $recent->get(1)->unit_cost) * 100 : 0;

            $ingredientsAnalysis[] = ['id' => $ing->id, 'name' => $ing->name, 'quantity' => (float) $item->quantity, 'uom' => $ing->unit_of_measure, 'unit_cost' => $unitCost, 'total_cost' => $totalCost, 'inflation_pct' => $infPct];
        }

        $modifierGroups = $product->modifiers()->where('type', ModifierType::OPTION->value)->get();
        foreach ($modifierGroups as $group) {
            $options = $group->options()->where('is_active', true)->whereNotNull('linked_product_id')->get();
            if ($options->isEmpty()) continue;
            $groupTotalWeight = $options->sum('historical_weight') ?: $options->count();

            foreach ($options as $opt) {
                $ing = $opt->linkedProduct;
                if (!$ing) continue;

                $takeRate = $groupTotalWeight > 0 ? ((float) ($opt->historical_weight ?: 1) / $groupTotalWeight) : 0;
                $weightedCost = (ProductCostingService::calculateBaseCost($ing) * (float) ($opt->linked_qty ?? 1)) * $takeRate;

                if ($weightedCost <= 0) continue;
                $totalRecipeCost += $weightedCost;

                $recent = $ing->costHistories->sortByDesc('id')->take(2)->values();
                $infPct = ($recent->get(0)?->unit_cost && $recent->get(1)?->unit_cost && (float) $recent->get(1)->unit_cost > 0)
                    ? (((float) $recent->get(0)->unit_cost - (float) $recent->get(1)->unit_cost) / (float) $recent->get(1)->unit_cost) * 100 : 0;

                $ingredientsAnalysis[] = ['id' => $ing->id, 'name' => "Opção: {$opt->name} (" . number_format($takeRate * 100, 0) . "%)", 'quantity' => (float) ($opt->linked_qty ?? 1), 'uom' => $ing->unit_of_measure, 'unit_cost' => ProductCostingService::calculateBaseCost($ing), 'total_cost' => $weightedCost, 'inflation_pct' => $infPct];
            }
        }

        foreach ($ingredientsAnalysis as &$ingData) {
            $sharePct = $totalRecipeCost > 0 ? ($ingData['total_cost'] / $totalRecipeCost) * 100 : 0;
            $ingData['share_pct'] = $sharePct;
            if ($ingData['inflation_pct'] >= 5) {
                $alerts[] = ['name' => $ingData['name'], 'type' => 'inflation', 'badge' => '+' . number_format($ingData['inflation_pct'], 1, ',', '.') . '% na NF-e', 'detail' => 'Alta de preço detectada.', 'severity' => $ingData['inflation_pct'] >= 15 ? 'danger' : 'warning'];
            } elseif ($sharePct >= 35) {
                $alerts[] = ['name' => $ingData['name'], 'type' => 'concentration', 'badge' => number_format($sharePct, 1, ',', '.') . '% do CMV', 'detail' => 'Insumo âncora: sensível na margem.', 'severity' => 'info'];
            }
        }

        usort($ingredientsAnalysis, fn ($a, $b) => $b['total_cost'] <=> $a['total_cost']);

        return [
            'has_history'       => $previousLog !== null,
            'period_days'       => $days,
            'current_cmv'       => $currentCmv,
            'cmv_diff_nominal'  => $cmvDiffNominal,
            'cmv_diff_pct'      => $cmvDiffPct,
            'cmv_status'        => $cmvDiffPct > 0.5 ? 'rising' : ($cmvDiffPct < -0.5 ? 'falling' : 'stable'),
            'current_margin'    => $currentMarginPct,
            'margin_diff_pp'    => $currentMarginPct - $prevMarginPct,
            'margin_status'     => ($currentMarginPct - $prevMarginPct) >= 0 ? 'gaining' : 'dropping',
            'top_ingredients'   => array_slice($ingredientsAnalysis, 0, 5),
            'alerts'            => $alerts,
        ];
    }
}
