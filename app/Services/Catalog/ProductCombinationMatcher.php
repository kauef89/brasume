<?php

namespace App\Services\Catalog;

use App\Models\Product;

class ProductCombinationMatcher
{
    /**
     * Retorna sugestões ordenadas de produtos que somam o valor alvo
     */
    public function findMatches(float $targetAmount): array
    {
        $target = round($targetAmount, 2);
        if ($target <= 0) return [];

        $products = Product::where('status', 'available')
            ->where(function ($q) {
                $q->where('dine_in_price', '>', 0)
                  ->orWhere('delivery_price', '>', 0);
            })
            ->get()
            ->map(function ($p) {
                $price = (float) ($p->dine_in_price ?: ($p->delivery_price ?: $p->promo_price ?: 0));
                return [
                    'id'         => $p->id,
                    'name'       => $p->name,
                    'kitchen_id' => $p->kitchen_id,
                    'price'      => round($price, 2),
                ];
            })
            ->filter(fn($p) => $p['price'] > 0 && $p['price'] <= $target)
            ->values();

        $suggestions = [];

        // 🚀 1. Produto único de valor exato (1x)
        $exactOnes = $products->filter(fn($p) => abs($p['price'] - $target) < 0.01);
        foreach ($exactOnes as $p) {
            $suggestions[] = [
                'type'  => 'exact_single',
                'label' => "1x {$p['name']} (R$ " . number_format($p['price'], 2, ',', '.') . ")",
                'total' => $p['price'],
                'items' => [
                    ['product_id' => $p['id'], 'name' => $p['name'], 'kitchen_id' => $p['kitchen_id'], 'quantity' => 1, 'unit_price' => $p['price']]
                ]
            ];
        }

        // 🚀 2. Dois produtos iguais (2x)
        $halfTarget = round($target / 2, 2);
        if (abs(($halfTarget * 2) - $target) < 0.01) {
            $exactPairs = $products->filter(fn($p) => abs($p['price'] - $halfTarget) < 0.01);
            foreach ($exactPairs as $p) {
                $suggestions[] = [
                    'type'  => 'exact_double',
                    'label' => "2x {$p['name']} (2x R$ " . number_format($p['price'], 2, ',', '.') . ")",
                    'total' => round($p['price'] * 2, 2),
                    'items' => [
                        ['product_id' => $p['id'], 'name' => $p['name'], 'kitchen_id' => $p['kitchen_id'], 'quantity' => 2, 'unit_price' => $p['price']]
                    ]
                ];
            }
        }

        // 🚀 3. Item mais caro + complemento
        $sortedDesc = $products->sortByDesc('price')->values();
        $pairMatches = [];

        foreach ($sortedDesc as $expensive) {
            if ($expensive['price'] >= $target) continue;

            $diff = round($target - $expensive['price'], 2);
            $complement = $products->first(fn($p) => $p['id'] !== $expensive['id'] && abs($p['price'] - $diff) < 0.01);

            if ($complement) {
                $pairKey = min($expensive['id'], $complement['id']) . '_' . max($expensive['id'], $complement['id']);
                if (!isset($pairMatches[$pairKey])) {
                    $pairMatches[$pairKey] = true;
                    $suggestions[] = [
                        'type'  => 'complementary_pair',
                        'label' => "1x {$expensive['name']} (R$ " . number_format($expensive['price'], 2, ',', '.') . ") + 1x {$complement['name']} (R$ " . number_format($complement['price'], 2, ',', '.') . ")",
                        'total' => round($expensive['price'] + $complement['price'], 2),
                        'items' => [
                            ['product_id' => $expensive['id'], 'name' => $expensive['name'], 'kitchen_id' => $expensive['kitchen_id'], 'quantity' => 1, 'unit_price' => $expensive['price']],
                            ['product_id' => $complement['id'], 'name' => $complement['name'], 'kitchen_id' => $complement['kitchen_id'], 'quantity' => 1, 'unit_price' => $complement['price']],
                        ]
                    ];
                }
            }
        }

        return array_slice($suggestions, 0, 6);
    }
}
