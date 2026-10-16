<?php

namespace App\Services\Orders;

use App\Models\OrderItem;
use Illuminate\Support\Collection;

class ComboProrationHelper
{
    /**
     * Obtém o preço de venda avulso do produto fora do combo,
     * acrescido de eventuais modificadores/adicionais pagos.
     */
    public static function getStandalonePrice(OrderItem $item, string $orderType = 'table'): float
    {
        $product = $item->product;
        $basePrice = 0.0;

        if ($product) {
            if ($orderType === 'delivery' && (float) ($product->delivery_price ?? 0) > 0) {
                $basePrice = (float) $product->delivery_price;
            } elseif (isset($product->dine_in_price) && (float) $product->dine_in_price > 0) {
                $basePrice = (float) $product->dine_in_price;
            } elseif (isset($product->price) && (float) $product->price > 0) {
                $basePrice = (float) $product->price;
            }
        }

        if ($basePrice <= 0 && (float) $item->unit_price > 0) {
            $basePrice = (float) $item->unit_price;
        }

        // Soma eventuais adicionais pagos de modificadores
        $modsExtra = 0.0;
        if ($item->relationLoaded('modifiers') && $item->modifiers->isNotEmpty()) {
            $itemQty = max(1, (float) $item->quantity);
            $totalModsCost = $item->modifiers->sum(fn($m) => ((float) $m->price) * ((float) ($m->quantity ?: 1)));
            $modsExtra = $totalModsCost / $itemQty;
        }

        return round($basePrice + $modsExtra, 2);
    }

    /**
     * Calcula o rateio proporcional do valor do combo pai entre todos os subitens.
     */
    public static function calculateProration(OrderItem $parent, ?Collection $children = null, string $orderType = 'table'): array
    {
        if ($children === null) {
            $children = $parent->relationLoaded('subItems') && $parent->subItems->isNotEmpty()
                ? $parent->subItems
                : OrderItem::with(['product', 'modifiers'])->where('parent_item_id', $parent->id)->get();
        }

        if ($children->isEmpty()) {
            return [];
        }

        $parentTotalPrice = (float) ($parent->total_price ?: ((float) $parent->unit_price * (float) $parent->quantity));

        // 1. Calcula o total de catálogo de todos os filhos somados
        $catalogValues = [];
        $totalCatalogSum = 0.0;

        foreach ($children as $child) {
            $qty = max(1, (float) $child->quantity);
            $standaloneUnit = self::getStandalonePrice($child, $orderType);
            $childCatalogTotal = $qty * ($standaloneUnit > 0 ? $standaloneUnit : 10.0);

            $catalogValues[$child->id] = [
                'qty'   => $qty,
                'unit'  => $standaloneUnit,
                'total' => $childCatalogTotal,
            ];
            $totalCatalogSum += $childCatalogTotal;
        }

        $proration = [];

        // 2. Rateio ponderado baseado no valor de mercado de cada item
        if ($totalCatalogSum > 0.001) {
            $factor = $parentTotalPrice / $totalCatalogSum;

            foreach ($children as $child) {
                $cData = $catalogValues[$child->id];
                $proratedUnit = round($cData['unit'] * $factor, 2);
                $proratedTotal = round($cData['total'] * $factor, 2);
                $weight = $cData['total'] / $totalCatalogSum;

                $proration[$child->id] = [
                    'catalog_unit_price'  => $cData['unit'],
                    'catalog_total'       => $cData['total'],
                    'weight'              => $weight,
                    'prorated_unit_price' => $proratedUnit,
                    'prorated_total'      => $proratedTotal,
                ];
            }
        } else {
            // Fallback: divisão por cotas simples caso nenhum item tenha preço de catálogo
            $totalQty = $children->sum(fn($c) => max(1, (float) $c->quantity));
            $unitShare = $totalQty > 0 ? round($parentTotalPrice / $totalQty, 2) : 0;

            foreach ($children as $child) {
                $qty = max(1, (float) $child->quantity);
                $proration[$child->id] = [
                    'catalog_unit_price'  => 0.0,
                    'catalog_total'       => 0.0,
                    'weight'              => $totalQty > 0 ? ($qty / $totalQty) : 0,
                    'prorated_unit_price' => $unitShare,
                    'prorated_total'      => round($unitShare * $qty, 2),
                ];
            }
        }

        return $proration;
    }
}
