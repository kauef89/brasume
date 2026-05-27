<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str; // <-- IMPORTAÇÃO PARA GERAR O SLUG
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $internalCategories = [
            ['name' => 'Carnes e Frios', 'sort_order' => 1],
            ['name' => 'Hortifruti', 'sort_order' => 2],
            ['name' => 'Mercearia e Condimentos', 'sort_order' => 3],
            ['name' => 'Laticínios', 'sort_order' => 4],
            ['name' => 'Embalagens e Descartáveis', 'sort_order' => 5],
            ['name' => 'Material de Limpeza e Uso', 'sort_order' => 6],
            ['name' => 'Bebidas (Estoque Central)', 'sort_order' => 7],
            ['name' => 'Preparo Interno', 'sort_order' => 9],
            ['name' => 'Pães & Massas', 'sort_order' => 8]
        ];

        foreach ($internalCategories as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name']],
                [
                    'slug' => Str::slug($cat['name']), // <-- GERA O SLUG AUTOMATICAMENTE!
                    'type' => 'internal',
                    'sort_order' => $cat['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
