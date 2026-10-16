<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\LegacyOrderItem;
use Illuminate\Support\Facades\DB;

class CustomerFavoritesService
{
    public function getFavorites(Customer $customer): array
    {
        // 1. Busca agregada (Agrupa e Conta primeiro - NOVOS)
        $newAggregates = OrderItem::whereHas('order', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)->where('status', 'closed');
            })
            ->whereNull('parent_item_id')
            ->select('product_id', DB::raw('MAX(id) as latest_id'), DB::raw('COUNT(*) as total_orders'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('latest_id');

        $newItems = [];
        if ($newAggregates->isNotEmpty()) {
            $newItems = OrderItem::with(['product.category', 'modifiers.modifierOption'])
                ->whereIn('id', $newAggregates->keys())
                ->get()
                ->mapWithKeys(function($item) use ($newAggregates) {
                    $productId = $item->product_id;
                    return [$productId => [
                        'id' => $productId,
                        'name' => $item->product_name ?: ($item->product?->name ?? 'Item'),
                        'category_name' => $item->product?->category?->name ?? 'Favoritos',
                        'unit_price' => (float) $item->unit_price,
                        'status' => $item->product?->status ?? 'available',
                        'track_inventory' => (bool) ($item->product?->track_inventory ?? false),
                        'current_stock' => (float) ($item->product?->current_stock ?? 0),
                        'notes' => $item->notes,
                        'modifiers' => $item->modifiers->map(fn($m) => [
                            'id' => $m->modifier_option_id,
                            'name' => $m->name,
                            'price' => (float) $m->price,
                            'quantity' => (int) $m->quantity
                        ])->toArray(),
                        'total_orders' => $newAggregates[$item->id]->total_orders
                    ]];
                })->toArray();
        }

        // 2. Busca agregada (LEGADOS - OTIMIZADO)
        $legacyCustomerIds = DB::table('legacy_customers')
            ->where('customer_id', $customer->id)
            ->pluck('id')
            ->all();

        $legacyOrderIdsQuery = DB::table('legacy_orders')
            ->where('customer_id', $customer->id);

        if (!empty($legacyCustomerIds)) {
            $legacyOrderIdsQuery->orWhereIn('legacy_customer_id', $legacyCustomerIds);
        }

        $legacyOrderIds = $legacyOrderIdsQuery->pluck('id')->all();

        $legacyAggregates = collect();
        if (!empty($legacyOrderIds)) {
            $foreignKey = method_exists(LegacyOrderItem::class, 'order')
                ? (new LegacyOrderItem)->order()->getForeignKeyName()
                : 'legacy_order_id';

            $legacyAggregates = LegacyOrderItem::whereIn($foreignKey, $legacyOrderIds)
                ->select('legacy_product_id', DB::raw('MAX(id) as latest_id'), DB::raw('COUNT(*) as total_orders'))
                ->groupBy('legacy_product_id')
                ->get()
                ->keyBy('latest_id');
        }

        $legacyItems = [];
        if ($legacyAggregates->isNotEmpty()) {
            $legacyRawItems = LegacyOrderItem::with([
                'legacyProduct.currentProduct.category',
                'modifiers'
            ])
            ->whereIn('id', $legacyAggregates->keys())
            ->get();

            foreach ($legacyRawItems as $item) {
                $legacyProd = $item->legacyProduct;
                $currentProd = $legacyProd ? $legacyProd->currentProduct : null;
                $totalOrders = $legacyAggregates[$item->id]->total_orders;

                if ($currentProd) {
                    $resolvedId = $currentProd->id;

                    if (isset($newItems[$resolvedId])) {
                        $newItems[$resolvedId]['total_orders'] += $totalOrders;
                        continue;
                    }

                    $basePrice = $currentProd->delivery_price > 0 ? $currentProd->delivery_price : $currentProd->dine_in_price;
                    $finalPrice = $currentProd->promo_price > 0 ? $currentProd->promo_price : $basePrice;

                    $legacyItems[$resolvedId] = [
                        'id' => $resolvedId,
                        'name' => $currentProd->name,
                        'category_name' => $currentProd->category?->name ?? 'Favoritos',
                        'unit_price' => (float) $finalPrice,
                        'status' => $currentProd->status,
                        'track_inventory' => (bool) $currentProd->track_inventory,
                        'current_stock' => (float) $currentProd->current_stock,
                        'notes' => '',
                        'modifiers' => $item->modifiers->map(function($m) {
                             $optionId = null;
                             if ($m->legacyModifier && $m->legacyModifier->modifier_option_id) {
                                 $optionId = $m->legacyModifier->modifier_option_id;
                             }
                             return [
                                'id' => $optionId,
                                'name' => $m->legacyModifier ? ($m->legacyModifier->currentOption->name ?? $m->name) : $m->name,
                                'price' => (float) ($m->price ?? 0),
                                'quantity' => (int) ($m->quantity ?? 1)
                            ];
                        })->toArray(),
                        'total_orders' => $totalOrders,
                    ];
                } else {
                    $legacyItems['legacy_' . $item->legacy_product_id] = [
                        'id' => $item->legacy_product_id,
                        'name' => $legacyProd?->name ?? 'Item Antigo',
                        'category_name' => 'Descontinuado',
                        'unit_price' => (float) $item->unit_price,
                        'status' => 'out_of_stock',
                        'track_inventory' => false,
                        'current_stock' => 0,
                        'notes' => '',
                        'modifiers' => [],
                        'total_orders' => $totalOrders,
                    ];
                }
            }
        }

        return collect($newItems)->concat(collect($legacyItems))
            ->sortByDesc('total_orders')
            ->take(12)
            ->values()
            ->toArray();
    }
}
