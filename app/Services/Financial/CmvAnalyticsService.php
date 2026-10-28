<?php

namespace App\Services\Financial;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ModifierOption;
use App\Services\Catalog\ProductCostingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CmvAnalyticsService
{
    protected Carbon $startDate;
    protected Carbon $endDate;
    protected string $regime;

    private static array $ingredientCostCache = [];
    private static array $modifierOptionCache = [];

    public function __construct(?Carbon $startDate = null, ?Carbon $endDate = null, string $regime = 'competencia')
    {
        $this->startDate = $startDate ?? Carbon::now()->startOfMonth();
        $this->endDate   = $endDate ?? Carbon::now()->endOfMonth();
        $this->regime    = $regime;
    }

    public function analyze(): array
    {
        // 1. DRE Canónico do Período
        $dre = (new DreService($this->startDate, $this->endDate, $this->regime))->calculate();
        $netRevenue   = (float) $dre['revenue']['net'];
        $grossRevenue = (float) $dre['revenue']['gross'];

        // 2. CMV Real (Desembolsado em Insumos e Embalagens)
        $actualIngredients = (float) $dre['cogs']['ingredients'];
        $actualPackaging   = (float) $dre['cogs']['packaging'];
        $actualCogsTotal   = $actualIngredients + $actualPackaging;
        $actualCogsRatio   = $netRevenue > 0 ? ($actualCogsTotal / $netRevenue) * 100 : 0;

        // 3. CMV Teórico com Explosão Real de Fichas Técnicas, Modificadores e Combos
        $theoreticalData      = $this->calculateDetailedTheoreticalCogs();
        $theoreticalCogsTotal = (float) $theoreticalData['total_cost'];
        $theoreticalCogsRatio = $netRevenue > 0 ? ($theoreticalCogsTotal / $netRevenue) * 100 : 0;

        // 4. Divergência / Fuga de Margem Real
        $varianceNominal           = $actualCogsTotal - $theoreticalCogsTotal;
        $varianceRatioOnNetRevenue = $netRevenue > 0 ? ($varianceNominal / $netRevenue) * 100 : 0;

        // 5. Matriz de Engenharia de Cardápio
        $menuMatrix = $this->buildMenuEngineeringMatrix($theoreticalData['items'], $netRevenue);

        return [
            'period' => [
                'start'  => $this->startDate->format('d/m/Y'),
                'end'    => $this->endDate->format('d/m/Y'),
                'regime' => $this->regime,
            ],
            'financial' => [
                'gross_revenue' => $grossRevenue,
                'net_revenue'   => $netRevenue,
            ],
            'cogs' => [
                'actual' => [
                    'ingredients' => $actualIngredients,
                    'packaging'   => $actualPackaging,
                    'total'       => $actualCogsTotal,
                    'ratio'       => round($actualCogsRatio, 2),
                ],
                'theoretical' => [
                    'total' => $theoreticalCogsTotal,
                    'ratio' => round($theoreticalCogsRatio, 2),
                ],
                'variance' => [
                    'nominal'        => $varianceNominal,
                    'ratio_on_net'   => round($varianceRatioOnNetRevenue, 2),
                    'is_waste_alert' => $varianceRatioOnNetRevenue > 5.0,
                    'status'         => $varianceNominal > 0 ? 'waste' : 'efficient',
                ],
            ],
            'menu_engineering' => $menuMatrix,
        ];
    }

    protected function calculateDetailedTheoreticalCogs(): array
    {
        ProductCostingService::clearCache();
        self::$ingredientCostCache = [];
        self::$modifierOptionCache = [];

        // Carrega todas as linhas de pedidos principais e seus subitens/modificadores
        $orderItems = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$this->startDate->startOfDay(), $this->endDate->endOfDay()])
            ->whereNotIn('orders.status', ['cancelled', 'canceled', 'rejected', 'draft'])
            ->whereNull('order_items.parent_item_id') // Apenas itens raiz (itens normais ou combos principais)
            ->with([
                'product',
                'modifiers.modifierOption',
                'subItems.product',
                'subItems.modifiers.modifierOption',
            ])
            ->select('order_items.*')
            ->get();

        $totalTheoreticalCost = 0.0;
        $aggregatedProducts = [];

        foreach ($orderItems as $item) {
            $itemCost = $this->calculateOrderItemCost($item);
            $totalTheoreticalCost += $itemCost;

            $productId = $item->product_id ?? 0;
            $productName = $item->product?->name ?? $item->product_name ?? 'Produto';

            if (!isset($aggregatedProducts[$productId])) {
                $aggregatedProducts[$productId] = [
                    'product_id'    => $productId,
                    'name'          => $productName,
                    'qty_sold'      => 0,
                    'total_revenue' => 0.0,
                    'total_cost'    => 0.0,
                ];
            }

            $aggregatedProducts[$productId]['qty_sold']      += (float) $item->quantity;
            $aggregatedProducts[$productId]['total_revenue']  += (float) ($item->total_price ?: ($item->unit_price * $item->quantity));
            $aggregatedProducts[$productId]['total_cost']     += $itemCost;
        }

        // Formatação final e cálculo de margens por item
        $itemsAnalysis = collect($aggregatedProducts)->map(function ($row) {
            $qty = max(1, $row['qty_sold']);
            $unitCost = $row['total_cost'] / $qty;
            $avgPrice = $row['total_revenue'] / $qty;
            $unitMargin = $avgPrice - $unitCost;
            $marginRatio = $avgPrice > 0 ? ($unitMargin / $avgPrice) * 100 : 0;

            return [
                'product_id'    => $row['product_id'],
                'name'          => $row['name'],
                'qty_sold'      => $row['qty_sold'],
                'total_revenue' => $row['total_revenue'],
                'unit_cost'     => $unitCost,
                'total_cost'    => $row['total_cost'],
                'unit_margin'   => $unitMargin,
                'margin_ratio'  => round($marginRatio, 1),
            ];
        })->values();

        return [
            'total_cost' => $totalTheoreticalCost,
            'items'      => $itemsAnalysis,
        ];
    }

    /**
     * Calcula recursivamente o custo exato de uma linha de pedido:
     * Ficha Base do Produto + Modificadores/Recheios + Subitens (se for Combo)
     */
    protected function calculateOrderItemCost(OrderItem $item): float
    {
        $lineCost = 0.0;
        $qty = max(1, (float) $item->quantity);

        // 1. Custo Base do Produto Pai (se possuir ficha técnica cadastrada)
        if ($item->product) {
            $baseUnitCost = ProductCostingService::calculateBaseCost($item->product, 'all');
            $lineCost += ($baseUnitCost * $qty);
        }

        // 2. Custo dos Modificadores / Recheios do Item Pai
        $lineCost += $this->calculateModifiersCost($item->modifiers);

        // 3. Se for um Combo (possui subitens filhos)
        if ($item->subItems && $item->subItems->isNotEmpty()) {
            foreach ($item->subItems as $childItem) {
                // Custo base do subitem filho
                if ($childItem->product) {
                    $childUnitCost = ProductCostingService::calculateBaseCost($childItem->product, 'all');
                    $childQty = max(1, (float) $childItem->quantity);
                    $lineCost += ($childUnitCost * $childQty);
                }

                // Custo dos modificadores / recheios do subitem filho
                $lineCost += $this->calculateModifiersCost($childItem->modifiers);
            }
        }

        return $lineCost;
    }

    /**
     * Soma o custo real dos insumos atrelados a cada modificador da comanda
     */
    protected function calculateModifiersCost($modifiers): float
    {
        if (!$modifiers || $modifiers->isEmpty()) {
            return 0.0;
        }

        $cost = 0.0;

        foreach ($modifiers as $mod) {
            $modQty = max(1, (float) ($mod->quantity ?? 1));
            $option = $mod->modifierOption ?? $this->getModifierOption($mod->modifier_option_id);

            if ($option && !empty($option->linked_product_id)) {
                $unitCost = $this->getIngredientUnitCost((int) $option->linked_product_id);
                $portionQty = (float) ($option->linked_qty ?? 1);

                $cost += ($unitCost * $portionQty * $modQty);
            }
        }

        return $cost;
    }

    protected function getModifierOption(?int $id): ?ModifierOption
    {
        if (!$id) return null;
        if (!isset(self::$modifierOptionCache[$id])) {
            self::$modifierOptionCache[$id] = ModifierOption::find($id);
        }
        return self::$modifierOptionCache[$id];
    }

    protected function getIngredientUnitCost(int $productId): float
    {
        if (!isset(self::$ingredientCostCache[$productId])) {
            $product = Product::find($productId);
            self::$ingredientCostCache[$productId] = $product
                ? ProductCostingService::calculateBaseCost($product, 'all')
                : 0.0;
        }
        return self::$ingredientCostCache[$productId];
    }

    protected function buildMenuEngineeringMatrix(Collection $items, float $netRevenue): array
    {
        if ($items->isEmpty()) {
            return [
                'benchmarks' => ['avg_volume' => 0, 'avg_margin' => 0],
                'stars'      => [],
                'plowhorses' => [],
                'puzzles'    => [],
                'dogs'       => [],
                'top_cost'   => [],
            ];
        }

        $avgQtySold = $items->avg('qty_sold');
        $avgMargin  = $items->avg('unit_margin');

        $classified = $items->map(function ($item) use ($avgQtySold, $avgMargin) {
            $highVolume = $item['qty_sold'] >= $avgQtySold;
            $highMargin = $item['unit_margin'] >= $avgMargin;

            $quadrant = match (true) {
                $highVolume && $highMargin   => 'star',
                $highVolume && !$highMargin  => 'plowhorse',
                !$highVolume && $highMargin  => 'puzzle',
                default                      => 'dog',
            };

            $item['quadrant'] = $quadrant;
            return $item;
        });

        return [
            'benchmarks' => [
                'avg_volume' => round($avgQtySold, 1),
                'avg_margin' => round($avgMargin, 2),
            ],
            'stars'      => $classified->where('quadrant', 'star')->values()->all(),
            'plowhorses' => $classified->where('quadrant', 'plowhorse')->values()->all(),
            'puzzles'    => $classified->where('quadrant', 'puzzle')->values()->all(),
            'dogs'       => $classified->where('quadrant', 'dog')->values()->all(),
            'top_cost'   => $classified->sortByDesc('total_cost')->take(8)->values()->all(),
        ];
    }
}
