<?php

namespace App\Services\Catalog;

use App\Models\OrderItem;

class TopSellingCombinationsService
{
    public function getTopCombinations(int $limit = 10): array
    {
        $items = OrderItem::with(['modifiers', 'product.category'])
            ->whereHas('order', function ($query) {
                $query->whereIn('orders.status', ['completed', 'closed', 'delivered']);
            })
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get();

        $grouped = $items->groupBy(function ($item) {
            $modIds = $item->modifiers->map(function($m) {
                return $m->modifier_option_id ?? $m->id;
            })->sort()->implode('-');

            return $item->product_id . '_mods_' . $modIds;
        });

        $ranked = $grouped->sortByDesc(fn($group) => $group->count())->take($limit);

        return $ranked->map(function ($group, $signature) {
            $baseItem = $group->first();

            return [
                'id' => $baseItem->product_id,
                'variant_signature' => $signature,
                'name' => $baseItem->product_name ?? $baseItem->name ?? $baseItem->product?->name ?? 'Destaque',
                'category_name' => $baseItem->product?->category?->name ?? 'Mais Pedidos',
                'unit_price' => (float) $baseItem->unit_price,
                'status' => $baseItem->product?->status ?? 'active',
                'track_inventory' => (bool) ($baseItem->product?->track_inventory ?? false),
                'current_stock' => $baseItem->product?->current_stock ?? 99,
                'modifiers' => collect($baseItem->modifiers)->map(fn($mod) => [
                    'id' => $mod->modifier_option_id ?? $mod->id,
                    'name' => $mod->name ?? '',
                    'quantity' => (float) ($mod->quantity ?? 1),
                    'price' => (float) ($mod->price ?? 0),
                ])->toArray(),
                'sales_count' => $group->count(),
            ];
        })->values()->toArray();
    }
}
