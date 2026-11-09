<?php
namespace App\Services\Catalog;

use App\Models\Product;
use App\Services\Catalog\Costing\BaseCostCalculator;
use App\Services\Catalog\Costing\CostVarianceAnalyzer;
use App\Services\Catalog\Costing\MarginCalculator;
use App\Services\Catalog\Costing\PredictiveCostCalculator;

class ProductCostingService
{
    public static function clearCache(): void { BaseCostCalculator::clearCache(); }
    public static function calculateBaseCost(Product $product, string $consumptionMode = "all"): float { return BaseCostCalculator::calculate($product, $consumptionMode); }
    public static function getPredictiveModifiersCost(Product $product): float { return PredictiveCostCalculator::calculate($product); }
    public static function getMarginData(Product $product): array { return MarginCalculator::getMarginData($product); }
    public static function simulatePricing(Product $product, array $selectedAddons, ?string $activeMode, ?float $customValue): array { return MarginCalculator::simulatePricing($product, $selectedAddons, $activeMode, $customValue); }
    public static function recordPriceSnapshot(Product $product, string $trigger = 'price_change'): void { CostVarianceAnalyzer::recordSnapshot($product, $trigger); }
    public static function getCostVarianceAnalysis(Product $product, int $days = 30): array { return CostVarianceAnalyzer::analyze($product, $days); }
}
