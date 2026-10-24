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
        $combinations = [];

        // 1. Busca os últimos itens de pedidos fechados/entregues carregando subitens do combo
        $orderItems = OrderItem::whereHas('order', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->whereIn('status', ['closed', 'completed', 'delivered']);
            })
            ->whereNull('parent_item_id')
            ->with([
                'product.category',
                'modifiers.modifierOption',
                'subItems.product.category',
                'subItems.modifiers.modifierOption',
            ])
            ->latest('id')
            ->take(300)
            ->get();

        foreach ($orderItems as $item) {
            if (!$item->product) {
                continue;
            }

            $productId = (int) $item->product_id;

            // Modificadores do item principal
            $normalizedMods = ($item->modifiers ?? collect())->map(function ($m) {
                return [
                    'id' => (int) ($m->modifier_option_id ?? $m->id),
                    'name' => trim((string) ($m->name ?? '')),
                    'price' => (float) ($m->price ?? 0),
                    'quantity' => (int) ($m->quantity ?? 1),
                ];
            })->sortBy(function ($m) {
                return ($m['id'] ? (string)$m['id'] : '') . '#' . mb_strtolower($m['name']);
            })->values()->toArray();

            // Mapeamento dos subitens se for combo
            $comboItemsData = [];
            if ($item->subItems && $item->subItems->isNotEmpty()) {
                $comboItemsData = $item->subItems->map(function ($sub) {
                    $subMods = ($sub->modifiers ?? collect())->map(function ($sm) {
                        return [
                            'id' => (int) ($sm->modifier_option_id ?? $sm->id),
                            'name' => trim((string) ($sm->name ?? '')),
                            'price' => (float) ($sm->price ?? 0),
                            'quantity' => (int) ($sm->quantity ?? 1),
                            'group_id' => $sm->modifier_group_id ? (int) $sm->modifier_group_id : null,
                        ];
                    })->sortBy(function ($sm) {
                        return ($sm['id'] ? (string)$sm['id'] : '') . '#' . mb_strtolower($sm['name']);
                    })->values()->toArray();

                    return [
                        'id' => (int) $sub->id,
                        'product_id' => (int) $sub->product_id,
                        'name' => $sub->product_name ?: ($sub->product?->name ?? 'Subitem'),
                        'group_id' => $sub->group_id ? (int) $sub->group_id : null,
                        'price' => (float) $sub->unit_price,
                        'quantity' => (int) ($sub->quantity ?? 1),
                        'notes' => trim((string) ($sub->notes ?? '')),
                        'modifiers' => $subMods,
                    ];
                })->values()->toArray();
            }

            $notes = trim((string) ($item->notes ?? ''));

            // Assinatura única por variação (produto + modificadores + subitens do combo + notas)
            $signature = $productId . '#' . md5(
                json_encode($normalizedMods) . '#' .
                json_encode($comboItemsData) . '#' .
                mb_strtolower($notes)
            );

            if (!isset($combinations[$signature])) {
                $combinations[$signature] = [
                    'id' => $productId,
                    'product_id' => $productId,
                    'variant_signature' => $signature,
                    'latest_id' => $item->id,
                    'name' => $item->product_name ?: ($item->product->name ?? 'Item'),
                    'category_name' => $item->product->category?->name ?? 'Favoritos',
                    'unit_price' => (float) $item->unit_price,
                    'status' => $item->product->status ?? 'available',
                    'track_inventory' => (bool) ($item->product->track_inventory ?? false),
                    'current_stock' => (float) ($item->product->current_stock ?? 0),
                    'notes' => $notes,
                    'modifiers' => $normalizedMods,
                    'combo_items' => $comboItemsData,
                    'total_orders' => 0,
                    'latest_order_at' => $item->created_at,
                ];
            }

            $combinations[$signature]['total_orders']++;
        }

        return collect($combinations)
            ->sortByDesc('total_orders')
            ->take(16)
            ->values()
            ->toArray();
    }
}