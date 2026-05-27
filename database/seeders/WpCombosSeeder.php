<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;
use App\Models\ComboGroup;
use App\Models\ComboItem;

class WpCombosSeeder extends Seeder
{
    public function run(): void
    {
        Product::unguard();
        ComboGroup::unguard();
        ComboItem::unguard();

        ComboItem::query()->delete();
        ComboGroup::query()->delete();

        $categoriaCombo = Category::firstOrCreate(
            ['name' => 'Combos'],
            [
                'slug' => Str::slug('Combos'),
                'type' => 'external',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        // =========================================================================
        // PARTE 1: CRIAR OS PRODUTOS DO TIPO "COMBO"
        // =========================================================================
        $csvCombos = base_path('storage/app/combos.csv');
        if (file_exists($csvCombos)) {
            $file = fopen($csvCombos, 'r');
            fgetcsv($file); // Pula cabeçalho
            $comboCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                // Previne linhas em branco ou lixo do CSV
                if (!isset($row[0]) || trim($row[0]) === '') continue;
                
                $id = trim($row[0]);
                
                // Se o ID não for um número, é um "pedaço" de uma descrição que quebrou a linha. Ignoramos!
                if (!is_numeric($id)) continue; 

                $nome = trim($row[1] ?? 'Combo Sem Nome');
                $status = trim($row[2] ?? 'publish') === 'publish' ? 'available' : 'unavailable';
                $descricao = trim($row[3] ?? '');
                
                // O ?? '0' protege contra o erro Undefined array key 4
                $precoRaw = trim($row[4] ?? '0');
                $preco = $precoRaw === '' ? 0 : (float) str_replace(',', '.', $precoRaw);

                Product::updateOrCreate(
                    ['id' => $id],
                    [
                        'name' => $nome,
                        'type' => 'combo',
                        'description' => $descricao,
                        'dine_in_price' => $preco,
                        'delivery_price' => $preco,
                        'status' => $status,
                        'category_id' => $categoriaCombo->id,
                        'track_inventory' => false,
                        'unit_of_measure' => 'UN',
                    ]
                );
                $comboCount++;
            }
            fclose($file);
            $this->command->info("PARTE 1: {$comboCount} Combos criados como Produtos com sucesso!");
        } else {
            $this->command->error("Arquivo combos.csv não encontrado.");
        }

        // =========================================================================
        // PARTE 2: MONTAR O WIZARD (GRUPOS E ITENS)
        // =========================================================================
        $csvSteps = base_path('storage/app/combo-steps.csv');
        if (file_exists($csvSteps)) {
            $file = fopen($csvSteps, 'r');
            fgetcsv($file); // Pula cabeçalho
            $groupCount = 0;
            $itemCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (!isset($row[0]) || trim($row[0]) === '') continue;

                $comboId = trim($row[0]);
                if (!is_numeric($comboId)) continue;

                $tituloPasso = trim($row[3] ?? 'Escolha uma opção');
                $tipoRestricao = trim($row[4] ?? 'products'); 
                $qtdPermitida = (int) trim($row[5] ?? '1');
                
                if (!Product::where('id', $comboId)->exists()) continue;

                $group = ComboGroup::create([
                    'product_id' => $comboId,
                    'name' => $tituloPasso,
                    'min_choices' => $qtdPermitida, 
                    'max_choices' => $qtdPermitida > 0 ? $qtdPermitida : 1,
                ]);
                $groupCount++;

                if ($tipoRestricao === 'products') {
                    $serializedIds = trim($row[6] ?? '');
                    
                    if (!empty($serializedIds) && $serializedIds !== 'null') {
                        $productIds = @unserialize($serializedIds); 
                        
                        if (is_array($productIds)) {
                            foreach ($productIds as $pId) {
                                if (Product::where('id', $pId)->exists()) {
                                    ComboItem::create([
                                        'combo_group_id' => $group->id,
                                        'product_id' => $pId,
                                        'additional_price' => 0,
                                    ]);
                                    $itemCount++;
                                }
                            }
                        }
                    }
                } elseif ($tipoRestricao === 'category') {
                    $categoryId = trim($row[7] ?? '');
                    
                    if (!empty($categoryId) && $categoryId !== 'null') {
                        if (Category::where('id', $categoryId)->exists()) {
                            ComboItem::create([
                                'combo_group_id' => $group->id,
                                'category_id' => $categoryId,
                                'additional_price' => 0,
                            ]);
                            $itemCount++;
                        }
                    }
                }
            }
            fclose($file);
            $this->command->info("PARTE 2: {$groupCount} Etapas do Wizard e {$itemCount} Itens internos vinculados perfeitamente!");
        } else {
            $this->command->error("Arquivo combo-steps.csv não encontrado.");
        }
    }
}