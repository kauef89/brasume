<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class WpCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $csvFile = base_path('storage/app/categories.csv');
        
        if (!file_exists($csvFile)) {
            $this->command->error("Ficheiro categories.csv não encontrado!");
            return;
        }

        $file = fopen($csvFile, 'r');
        fgetcsv($file); // Pula o cabeçalho

        $categoriesData = [];

        // Lê todos os dados para a memória
        while (($row = fgetcsv($file)) !== false) {
            if (!isset($row[0]) || trim($row[0]) === '') continue;

            $categoriesData[] = [
                'id' => trim($row[0]),
                'name' => trim($row[1]),
                'slug' => trim($row[2]),
                'parent_id' => (int) trim($row[3]),
                'description' => trim($row[4]),
            ];
        }
        fclose($file);

        // ==========================================
        // VOLTA 1: Cria todas as categorias (sem pai)
        // ==========================================
        $count = 0;
        foreach ($categoriesData as $data) {
            Category::updateOrCreate(
                ['id' => $data['id']],
                [
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'description' => $data['description'],
                    'type' => 'external',
                    'is_active' => true,
                    'sort_order' => 0,
                    'parent_id' => null, // Ignoramos o pai por enquanto
                ]
            );
            $count++;
        }

        // ==========================================
        // VOLTA 2: Atualiza a hierarquia (Quem é filho de quem)
        // ==========================================
        $parentCount = 0;
        foreach ($categoriesData as $data) {
            if ($data['parent_id'] > 0) {
                $cat = Category::find($data['id']);
                // Garante que a categoria pai realmente existe no banco antes de ligar
                if ($cat && Category::where('id', $data['parent_id'])->exists()) {
                    $cat->update(['parent_id' => $data['parent_id']]);
                    $parentCount++;
                }
            }
        }

        $this->command->info("PERFEITO! {$count} Categorias criadas e {$parentCount} subcategorias organizadas na hierarquia!");
    }
}