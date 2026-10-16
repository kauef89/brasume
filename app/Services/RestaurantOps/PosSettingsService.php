<?php

namespace App\Services\RestaurantOps;

use App\Models\Category;
use App\Models\Product;
use App\Models\CompanySetting;
use App\Events\CatalogUpdated;
use Illuminate\Support\Facades\Cache;

class PosSettingsService
{
    public function getSettingsData(): array
    {
    $categories = \App\Models\Category::whereNull('parent_id')
        ->where('type', 'external')
        ->with([
            'children' => fn($q) => $q->where('type', 'external')->orderBy('sort_order', 'asc'),
            'children.children' => fn($q) => $q->where('type', 'external')->orderBy('sort_order', 'asc'),
            'children.children.children' => fn($q) => $q->where('type', 'external')->orderBy('sort_order', 'asc'),
            'children.children.children.children' => fn($q) => $q->where('type', 'external')->orderBy('sort_order', 'asc'),
        ])
        ->orderBy('sort_order', 'asc')
        ->get();

    return [
        'categories' => $categories];
    }

    public function reorderItems(string $type, array $items): void
    {
        if ($type === 'categories') {
            \Illuminate\Support\Facades\DB::transaction(function () use ($items) {
                $this->saveCategoryTree($items, null);
            });

            // Transmite o evento para atualizar os WebSockets/KDS se necessário
            $tenant = tenant('id') ?? 'central';
            broadcast(new \App\Events\CatalogUpdated($tenant));
        }
    }

    private function saveCategoryTree(array $nodes, ?int $parentId = null): void
    {
    foreach ($nodes as $index => $node) {
        if (!isset($node['id'])) continue;

        // 🚀 SONDA LARAVEL 2: O que ele vai gravar no banco?
        \Illuminate\Support\Facades\Log::info("💾 GRAVANDO NÓ ID: {$node['id']} | Ordem: {$index} | Pai: " . ($parentId ?? 'NULL'));

        \App\Models\Category::where('id', $node['id'])->update([
            'sort_order' => $index,
            'parent_id'  => $parentId,
        ]);

        if (!empty($node['children']) && is_array($node['children'])) {
            $this->saveCategoryTree($node['children'], (int) $node['id']);
        }
    }
    }
}
