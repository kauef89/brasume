<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class WpProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Certifique-se de que o nome do ficheiro bate certo com o que fez upload!
        $csvFile = base_path('storage/app/products-categoies.csv'); 
        
        if (!file_exists($csvFile)) {
            $this->command->error("Ficheiro products-categoies.csv não encontrado!");
            return;
        }

        $file = fopen($csvFile, 'r');
        fgetcsv($file); // Pula o cabeçalho
        $count = 0;

        while (($row = fgetcsv($file)) !== false) {
            // A coluna 0 é o ID do produto, a coluna 2 é o Nome da Categoria
            if (!isset($row[0]) || !isset($row[2]) || trim($row[0]) === '') continue;

            $productId = trim($row[0]);
            $categoryName = trim($row[2]);

            if (empty($categoryName)) continue;

            // Procura a categoria pelo nome exato que veio do CSV
            $category = Category::where('name', $categoryName)->first();

            if ($category) {
                // Se encontrou a categoria, atualiza o produto com o ID dela
                $updated = Product::where('id', $productId)->update([
                    'category_id' => $category->id
                ]);

                if ($updated) {
                    $count++;
                }
            }
        }

        fclose($file);
        
        // Limpeza opcional: Se a categoria "Cardápio Geral" ficou vazia, podemos avisar
        $produtosPerdidos = Product::whereHas('category', function($q) {
            $q->where('name', 'Cardápio Geral');
        })->count();

        $this->command->info("ORGANIZAÇÃO CONCLUÍDA: {$count} Produtos movidos para as categorias corretas!");
        if ($produtosPerdidos > 0) {
            $this->command->warn("Atenção: {$produtosPerdidos} produtos continuam na categoria 'Cardápio Geral' pois não tinham categoria no CSV.");
        }
    }
}