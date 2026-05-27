<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;

class WpProductsSeeder extends Seeder
{
    public function run(): void
    {
        $csvFile = base_path('storage/app/products-with-modifiers.csv');
        
        if (!file_exists($csvFile)) {
            $this->command->error("Ficheiro products-with-modifiers.csv não encontrado!");
            return;
        }

        // Garante que existe pelo menos uma categoria para não dar erro
        // (A VÍRGULA CRÍTICA ESTÁ AQUI NO FINAL DA LINHA DO NOME)
        $categoriaPadrao = Category::firstOrCreate(
            ['name' => 'Cardápio Geral'],
            [
                'slug' => Str::slug('Cardápio Geral'),
                'type' => 'external', 
                'is_active' => true, 
                'sort_order' => 0,
            ]
        );

        $file = fopen($csvFile, 'r');
        fgetcsv($file); // Pula o cabeçalho
        $count = 0;
        $syncCount = 0;

        while (($row = fgetcsv($file)) !== false) {
            // Verifica se tem o ID na coluna 0
            if (!isset($row[0]) || trim($row[0]) === '') {
                continue;
            }

            $id = trim($row[0]);
            
            // Tratamento de preços (Troca vírgula por ponto)
            $precoBase = (float) str_replace(',', '.', trim($row[2] ?? '0'));
            $precoPromo = (float) str_replace(',', '.', trim($row[3] ?? '0'));
            $custoBase = (float) str_replace(',', '.', trim($row[22] ?? '0'));
            
            // Tratamento de Status
            $statusRaw = strtolower(trim($row[7] ?? 'disponivel'));
            $statusFinal = match($statusRaw) {
                'indisponivel' => 'unavailable',
                'oculto' => 'hidden',
                default => 'available',
            };

            // Booleanos (1 ou 0)
            $controlaEstoque = trim($row[4] ?? '0') === '1';
            $exigePreparo = trim($row[8] ?? '0') === '1';
            $exibirDelivery = trim($row[10] ?? '1') === '1';
            $exibirMesa = trim($row[11] ?? '1') === '1';
            $apenasAdultos = trim($row[12] ?? '0') === '1';
            $geraFidelidade = trim($row[13] ?? '0') === '1';

            // Cria ou Atualiza o Produto FORÇANDO o ID do WordPress
            $product = Product::updateOrCreate(
                ['id' => $id],
                [
                    'category_id' => $categoriaPadrao->id,
                    'name' => trim($row[1] ?? 'Produto Sem Nome'),
                    'type' => trim($row[20] ?? 'resale'),
                    'unit_of_measure' => strtoupper(trim($row[21] ?? 'UN')),
                    
                    // Preços
                    'base_cost' => $custoBase,
                    'dine_in_price' => $precoBase,
                    'delivery_price' => $precoBase, // Copiamos o base para o delivery por padrão
                    'promo_price' => $precoPromo > 0 ? $precoPromo : null,
                    
                    // Estoque
                    'track_inventory' => $controlaEstoque,
                    'current_stock' => (float) str_replace(',', '.', trim($row[5] ?? '0')),
                    'min_stock' => (float) str_replace(',', '.', trim($row[6] ?? '0')),
                    
                    // Configurações e Regras
                    'status' => $statusFinal,
                    'requires_preparation' => $exigePreparo,
                    'show_on_delivery' => $exibirDelivery,
                    'show_on_menu' => $exibirMesa,
                    'is_age_restricted' => $apenasAdultos,
                    'loyalty_reward' => $geraFidelidade,
                    'print_station_id' => trim($row[9] ?? null) ?: null,
                    
                    // Fiscal
                    'fiscal_ncm' => trim($row[14] ?? ''),
                    'fiscal_cest' => trim($row[15] ?? ''),
                    'fiscal_cfop' => trim($row[16] ?? ''),
                    'fiscal_origin' => trim($row[17] ?? '0'),
                    'fiscal_csosn' => trim($row[18] ?? ''),
                ]
            );

            $count++;

            // ==========================================
            // A MÁGICA DA LIGAÇÃO COM OS MODIFICADORES
            // ==========================================
            $modIdsString = trim(trim($row[19] ?? ''), '"');
            if (!empty($modIdsString)) {
                $modifierIds = array_filter(array_map('trim', explode(',', $modIdsString)));
                if (count($modifierIds) > 0) {
                    $product->modifiers()->syncWithoutDetaching($modifierIds);
                    $syncCount++;
                }
            }
        }

        fclose($file);
        $this->command->info("ESPETÁCULO! {$count} Produtos importados e {$syncCount} deles já ligados aos Modificadores!");
    }
}