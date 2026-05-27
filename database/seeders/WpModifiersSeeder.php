<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductModifier;

class WpModifiersSeeder extends Seeder
{
    public function run(): void
    {
        // =========================================================================
        // PARTE 1: CRIAR OS MODIFICADORES FORÇANDO OS IDs ORIGINAIS DO WORDPRESS
        // =========================================================================
        $csvModifiers = base_path('storage/app/modifiers-with-id.csv');
        
        if (file_exists($csvModifiers)) {
            $file = fopen($csvModifiers, 'r');
            fgetcsv($file); // Pula cabeçalho
            $modCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                // Previne linhas em branco ou mal formatadas
                if (count($row) < 9) continue;

                $idGrupo = trim($row[0]);
                $grupoNome = trim($row[1]);
                $min = (int) trim($row[3]);
                $max = (int) trim($row[4]);
                $ordem = (int) trim($row[5]);
                $opcaoNome = trim($row[6]);
                
                // Tratamento de preço (troca vírgula por ponto para o banco de dados)
                $precoRaw = trim($row[7]);
                $preco = $precoRaw === '' ? 0 : (float) str_replace(',', '.', $precoRaw);
                
                $status = trim($row[8]) === 'disponivel';

                if (empty($idGrupo) || empty($grupoNome) || empty($opcaoNome)) continue;

                // Define o tipo
                $type = 'add_on';
                if (stripos($grupoNome, 'Obs.') !== false) $type = 'observation';
                if (stripos($grupoNome, 'Ingrediente') !== false) $type = 'ingredient';

                // Cria o Grupo (forçando o ID antigo!)
                $group = ProductModifier::updateOrCreate(
                    ['id' => $idGrupo],
                    [
                        'name' => $grupoNome,
                        'type' => $type,
                        'is_active' => true,
                        'min_selections' => $min,
                        'max_selections' => $max > 0 ? $max : 10,
                    ]
                );

                // Cria as Opções de Resposta
                $group->options()->updateOrCreate(
                    ['name' => $opcaoNome],
                    [
                        'price' => $preco,
                        'sort_order' => $ordem,
                        'is_active' => $status,
                    ]
                );

                $modCount++;
            }
            fclose($file);
            $this->command->info("PARTE 1 CONCLUÍDA: {$modCount} Opções criadas preservando os IDs originais!");
        } else {
            $this->command->error("Ficheiro modifiers-with-id.csv não encontrado!");
        }


        // =========================================================================
        // PARTE 2: LIGAR OS PRODUTOS AOS SEUS MODIFICADORES
        // =========================================================================
        $csvProducts = base_path('storage/app/products-with-modifiers.csv');
        
        if (file_exists($csvProducts)) {
            $file = fopen($csvProducts, 'r');
            fgetcsv($file); // Pula cabeçalho
            $syncCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                // A coluna 0 é o ID do produto, a 19 é a lista de grupos
                if (!isset($row[0]) || !isset($row[19])) continue;

                $productId = trim($row[0]);
                // Remove possíveis aspas que o Excel coloca ao redor de listas com vírgula
                $modIdsString = trim(trim($row[19]), '"'); 

                if (empty($modIdsString)) continue;

                // Transforma "1156, 1165" num array real do PHP
                $modifierIds = array_filter(array_map('trim', explode(',', $modIdsString)));

                if (count($modifierIds) > 0) {
                    $product = Product::find($productId);
                    if ($product) {
                        // Faz a ligação na tabela pivot 'product_modifier' perfeitamente!
                        $product->modifiers()->syncWithoutDetaching($modifierIds);
                        $syncCount++;
                    }
                }
            }
            fclose($file);
            $this->command->info("PARTE 2 CONCLUÍDA: {$syncCount} Produtos ligados aos seus modificadores exatos!");
        } else {
            $this->command->error("Ficheiro products-with-modifiers.csv não encontrado!");
        }
    }
}