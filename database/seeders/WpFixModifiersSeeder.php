<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductModifier;
use App\Models\ModifierOption;

class WpFixModifiersSeeder extends Seeder
{
    public function run(): void
    {
        // =========================================================================
        // PASSO 1: FAXINA (Apagar todos os modificadores duplicados)
        // =========================================================================
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('modifier_options')->truncate();
        DB::table('product_modifier')->truncate();
        DB::table('modifier_groups')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->warn("Faxina concluída: Modificadores duplicados removidos.");

        // =========================================================================
        // PASSO 2: DESLIGAR A TRAVA E IMPORTAR COM OS IDs ORIGINAIS
        // =========================================================================
        
        // A MAGIA ESTÁ AQUI: Isso permite injetarmos o ID manualmente!
        ProductModifier::unguard(); 
        ModifierOption::unguard();

        $csvModifiers = base_path('storage/app/modifiers-with-id.csv');
        
        if (file_exists($csvModifiers)) {
            $file = fopen($csvModifiers, 'r');
            fgetcsv($file); // Pula cabeçalho
            $modCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (count($row) < 9) continue;

                $idGrupo = trim($row[0]);
                $grupoNome = trim($row[1]);
                $min = (int) trim($row[3]);
                $max = (int) trim($row[4]);
                $ordem = (int) trim($row[5]);
                $opcaoNome = trim($row[6]);
                
                $precoRaw = trim($row[7]);
                $preco = $precoRaw === '' ? 0 : (float) str_replace(',', '.', $precoRaw);
                $status = trim($row[8]) === 'disponivel';

                if (empty($idGrupo) || empty($grupoNome) || empty($opcaoNome)) continue;

                $type = 'add_on';
                if (stripos($grupoNome, 'Obs.') !== false) $type = 'observation';
                if (stripos($grupoNome, 'Ingrediente') !== false) $type = 'ingredient';

                // Usamos firstOrCreate para garantir que o Grupo só é criado UMA vez
                $group = ProductModifier::firstOrCreate(
                    ['id' => $idGrupo], // Busca
                    [
                        'id' => $idGrupo, // FORÇA O ID DO WORDPRESS AQUI!
                        'name' => $grupoNome,
                        'type' => $type,
                        'is_active' => true,
                        'min_selections' => $min,
                        'max_selections' => $max > 0 ? $max : 10,
                    ]
                );

                // Cria as opções dentro do grupo
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
            $this->command->info("SUCESSO: {$modCount} opções criadas COM OS IDs EXATOS (Sem duplicação)!");
        }

        // =========================================================================
        // PASSO 3: REFAZER OS LINKS DOS PRODUTOS
        // =========================================================================
        $csvProducts = base_path('storage/app/products-with-modifiers.csv');
        
        if (file_exists($csvProducts)) {
            $file = fopen($csvProducts, 'r');
            fgetcsv($file);
            $syncCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (!isset($row[0]) || !isset($row[19])) continue;

                $productId = trim($row[0]);
                $modIdsString = trim(trim($row[19]), '"'); 

                if (empty($modIdsString)) continue;

                $modifierIds = array_filter(array_map('trim', explode(',', $modIdsString)));

                if (count($modifierIds) > 0) {
                    $product = Product::find($productId);
                    if ($product) {
                        // Agora sim, os IDs existem no banco e o link vai funcionar!
                        $product->modifiers()->syncWithoutDetaching($modifierIds);
                        $syncCount++;
                    }
                }
            }
            fclose($file);
            $this->command->info("ESPETÁCULO: {$syncCount} Produtos linkados aos modificadores corretos!");
        }
    }
}