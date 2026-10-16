<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;

class TreeCategory extends Page
{
    protected static string $resource = CategoryResource::class;

    protected string $view = 'filament.resources.categories.pages.tree-category';

    protected static ?string $title = 'Organização do Cardápio';

    public function getCategories()
    {
        return Category::whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    // 🚀 MÉTODO QUE SALVA O DRAG AND DROP
    public function updateCategoryOrder(array $treeData, ?int $parentId = null): void
    {
        foreach ($treeData as $index => $node) {
            Category::where('id', $node['id'])->update([
                'sort_order' => $index,
                'parent_id'  => $parentId,
            ]);

            // Se o nó arrastado tem subcategorias dentro dele, atualiza os filhos recursivamente
            if (! empty($node['children'])) {
                $this->updateCategoryOrder($node['children'], $node['id']);
            }
        }

        Notification::make()
            ->title('Cardápio reordenado com sucesso!')
            ->success()
            ->send();
    }
}
