<?php

namespace App\Services\Catalog\Costing;

use App\Enums\CostChainStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\TechnicalSheet;

class CostChainAnalyzer
{
    private static array $analysisCache = [];

    public static function clearCache(): void
    {
        self::$analysisCache = [];
    }

    /**
     * Avalia em profundidade a árvore de custos e ficha técnica de um produto.
     * Retorna ['status' => CostChainStatus, 'issues' => array, 'cost' => float].
     */
    public static function analyze(Product $product, array &$visited = []): array
    {
        $cacheKey = $product->id;
        if (isset(self::$analysisCache[$cacheKey]) && empty($visited)) {
            return self::$analysisCache[$cacheKey];
        }

        // Proteção contra ciclos de recursão infinita
        if (isset($visited[$product->id])) {
            return [
                'status' => CostChainStatus::ORPHAN_PREP,
                'issues' => ["Loop cíclico de dependência detectado no produto '{$product->name}'."],
                'cost'   => (float) ($product->base_cost ?? 0),
            ];
        }

        $type = $product->type instanceof ProductType ? $product->type : ProductType::tryFrom((string)$product->type);
        $typeVal = $type?->value ?? (string) $product->type;

        // 1. Itens isentos de cadeia produtiva de CMV
        if (in_array($typeVal, [ProductType::SERVICE->value, ProductType::CONSUMABLE->value], true)) {
            $result = [
                'status' => CostChainStatus::EXEMPT,
                'issues' => [],
                'cost'   => 0.0,
            ];
            if (empty($visited)) self::$analysisCache[$cacheKey] = $result;
            return $result;
        }

        // 2. Itens de Revenda Direta
        if ($typeVal === ProductType::RESALE->value) {
            $unitCost = self::resolveDirectCost($product);
            if ($unitCost <= 0.0001) {
                $result = [
                    'status' => CostChainStatus::ZERO_COST,
                    'issues' => ["Item de revenda sem custo unitário ou histórico de compra registrado."],
                    'cost'   => 0.0,
                ];
            } else {
                $result = [
                    'status' => CostChainStatus::DIRECT_RESALE,
                    'issues' => [],
                    'cost'   => $unitCost,
                ];
            }
            if (empty($visited)) self::$analysisCache[$cacheKey] = $result;
            return $result;
        }

        // 3. Matéria-prima ou Embalagem cadastrada isoladamente
        if (in_array($typeVal, [ProductType::INGREDIENT->value, ProductType::PACKAGING->value], true)) {
            $hasSheets = TechnicalSheet::where('parent_product_id', $product->id)->where('is_active', true)->exists();
            if (!$hasSheets) {
                $unitCost = self::resolveDirectCost($product);
                $status = ($unitCost > 0.0001) ? CostChainStatus::HEALTHY : CostChainStatus::ZERO_COST;
                $issues = ($unitCost <= 0.0001) ? ["Insumo/embalagem com custo unitário zerado (R$ 0,00)."] : [];
                $result = [
                    'status' => $status,
                    'issues' => $issues,
                    'cost'   => $unitCost,
                ];
                if (empty($visited)) self::$analysisCache[$cacheKey] = $result;
                return $result;
            }
        }

        // 4. Itens com Ficha Técnica (DISH, COMBO, MANUFACTURED ou insumos com receita)
        $visited[$product->id] = true;

        $sheets = TechnicalSheet::where('parent_product_id', $product->id)
            ->where('is_active', true)
            ->with(['ingredient.latestCostHistory'])
            ->get();

        if ($sheets->isEmpty()) {
            unset($visited[$product->id]);
            $result = [
                'status' => CostChainStatus::UNCONFIGURED,
                'issues' => ["Produto sem insumos cadastrados na ficha técnica."],
                'cost'   => self::resolveDirectCost($product),
            ];
            if (empty($visited)) self::$analysisCache[$cacheKey] = $result;
            return $result;
        }

        $overallStatus = CostChainStatus::HEALTHY;
        $issues = [];
        $totalCost = 0.0;

        foreach ($sheets as $item) {
            $child = $item->ingredient ?? Product::find($item->ingredient_product_id);
            $qty = (float) $item->quantity;

            if (!$child) {
                $overallStatus = self::escalateStatus($overallStatus, CostChainStatus::ORPHAN_PREP);
                $issues[] = "Insumo #{$item->ingredient_product_id} não localizado no catálogo.";
                continue;
            }

            $childType = $child->type instanceof ProductType ? $child->type : ProductType::tryFrom((string)$child->type);
            $childTypeVal = $childType?->value ?? (string) $child->type;

            $isManufactured = ($childTypeVal === ProductType::MANUFACTURED->value)
                || TechnicalSheet::where('parent_product_id', $child->id)->where('is_active', true)->exists();

            if ($isManufactured) {
                $childAnalysis = self::analyze($child, $visited);

                if ($childAnalysis['status'] === CostChainStatus::UNCONFIGURED) {
                    $overallStatus = self::escalateStatus($overallStatus, CostChainStatus::ORPHAN_PREP);
                    $issues[] = "Pré-preparo '{$child->name}' não possui ficha técnica cadastrada.";
                } elseif ($childAnalysis['status'] === CostChainStatus::ORPHAN_PREP) {
                    $overallStatus = self::escalateStatus($overallStatus, CostChainStatus::ORPHAN_PREP);
                    foreach ($childAnalysis['issues'] as $subIssue) {
                        $issues[] = "Em '{$child->name}': {$subIssue}";
                    }
                } elseif ($childAnalysis['status'] === CostChainStatus::ZERO_COST) {
                    $overallStatus = self::escalateStatus($overallStatus, CostChainStatus::ZERO_COST);
                    foreach ($childAnalysis['issues'] as $subIssue) {
                        $issues[] = "Em '{$child->name}': {$subIssue}";
                    }
                }

                $childUnitCost = (float) $childAnalysis['cost'];
                if ($childUnitCost <= 0.0001 && $overallStatus !== CostChainStatus::ORPHAN_PREP) {
                    $overallStatus = self::escalateStatus($overallStatus, CostChainStatus::ZERO_COST);
                    $issues[] = "Pré-preparo '{$child->name}' resultou em custo unitário R$ 0,00.";
                }

                $totalCost += ($childUnitCost * $qty);
            } else {
                $childUnitCost = self::resolveDirectCost($child);

                if ($childUnitCost <= 0.0001) {
                    $overallStatus = self::escalateStatus($overallStatus, CostChainStatus::ZERO_COST);
                    $issues[] = "Insumo '{$child->name}' sem custo cadastrado (R$ 0,00).";
                }

                $totalCost += ($childUnitCost * $qty);
            }
        }

        unset($visited[$product->id]);

        $yield = max((float) ($product->recipe_yield ?? 1), 1.0);
        $finalCost = round($totalCost / $yield, 4);

        $result = [
            'status' => $overallStatus,
            'issues' => array_values(array_unique($issues)),
            'cost'   => $finalCost,
        ];

        if (empty($visited)) {
            self::$analysisCache[$cacheKey] = $result;
        }

        return $result;
    }

    private static function resolveDirectCost(Product $product): float
    {
        $latest = $product->latestCostHistory ?? ProductCostHistory::where('product_id', $product->id)->latest('id')->first();
        if ($latest && (float)$latest->unit_cost > 0) {
            return (float) $latest->unit_cost;
        }

        return (float) ($product->base_cost ?? $product->cost_price ?? 0);
    }

    private static function escalateStatus(CostChainStatus $current, CostChainStatus $new): CostChainStatus
    {
        $priority = [
            CostChainStatus::ORPHAN_PREP->value   => 4,
            CostChainStatus::ZERO_COST->value     => 3,
            CostChainStatus::UNCONFIGURED->value  => 2,
            CostChainStatus::DIRECT_RESALE->value => 1,
            CostChainStatus::HEALTHY->value       => 1,
            CostChainStatus::EXEMPT->value        => 0,
        ];

        return ($priority[$new->value] ?? 0) > ($priority[$current->value] ?? 0) ? $new : $current;
    }
}