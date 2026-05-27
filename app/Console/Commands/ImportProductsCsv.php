<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Tenant;

class ImportProductsCsv extends Command
{
    // O comando que vamos digitar no terminal:
    protected $signature = 'import:products {tenant} {file}';
    protected $description = 'Importa produtos de um CSV para um Tenant específico';

    public function handle()
    {
        $tenantId = $this->argument('tenant');
        $filePath = base_path($this->argument('file'));

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return;
        }

        // Inicializa a conexão com o banco de dados da lanchonete (nativa)
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant {$tenantId} não encontrado!");
            return;
        }
        tenancy()->initialize($tenant);

        $this->info("Importando produtos para o tenant: {$tenantId}...");

        $file = fopen($filePath, 'r');
        $header = fgetcsv($file, 1000, ',');

        $count = 0;
        while (($row = fgetcsv($file, 1000, ',')) !== false) {
            $data = array_combine($header, $row);

            // Traduz o status do português pro nosso padrão gringo
            $statusMap = [
                'disponivel' => 'available',
                'indisponivel' => 'unavailable',
                'oculto' => 'hidden'
            ];

            // Função para limpar vírgulas de preços ("10,5" -> 10.5)
            $formatDecimal = fn($val) => (float) str_replace(',', '.', $val);

            Product::create([
                // Como o CSV não tem Categoria, deixamos null por enquanto
                'name' => $data['Nome'],
                'base_cost' => $formatDecimal($data['Custo Base']),
                'dine_in_price' => $formatDecimal($data['Preco Base']),
                'delivery_price' => $formatDecimal($data['Preco Base']), // Repetindo para o delivery
                'promo_price' => $formatDecimal($data['Preco Promocional']) > 0 ? $formatDecimal($data['Preco Promocional']) : null,
                
                'track_inventory' => (bool) $data['Controlar Estoque (1/0)'],
                'current_stock' => $formatDecimal($data['Qtd Estoque']),
                'min_stock' => $formatDecimal($data['Estoque Minimo']),
                'status' => $statusMap[strtolower($data['Status (disponivel/indisponivel/oculto)'])] ?? 'available',
                
                'requires_preparation' => (bool) $data['Exige Preparo (1/0)'],
                'print_station_id' => $data['ID Estacao de Impressao'] ?: null,
                
                'show_on_delivery' => (bool) $data['Exibir no Delivery (1/0)'],
                'show_on_menu' => (bool) $data['Exibir na Mesa (1/0)'],
                'is_age_restricted' => (bool) $data['Apenas Maiores de 18 (1/0)'],
                'loyalty_reward' => (bool) $data['Recompensa Fidelidade (1/0)'],
                
                // Fiscal
                'fiscal_ncm' => $data['Fiscal NCM'] ?: null,
                'fiscal_cest' => $data['Fiscal CEST'] ?: null,
                'fiscal_cfop' => $data['Fiscal CFOP'] ?: null,
                'fiscal_origin' => $data['Fiscal Origem'] ?: null,
                'fiscal_csosn' => $data['Fiscal CSOSN'] ?: null,
                
                'type' => $data['Tipo de Item'] ?: 'resale',
                'unit_of_measure' => $data['Unidade de Medida'] ?: 'un',
                'conversion_factor' => $formatDecimal($data['Fator Conversao']) ?: 1,

                // Salva os legados como JSON para processarmos depois
                'legacy_modifier_groups' => json_encode(explode(',', $data['IDs Grupos Adicionais (separados por virgula)'])),
                'legacy_recipe' => json_encode($data['Ficha Tecnica (ID:Qtd|ID:Qtd)']),
            ]);

            $count++;
        }

        fclose($file);
        $this->info("Sucesso! {$count} produtos importados.");
    }
}