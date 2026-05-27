<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class ImportSuppliersSeeder extends Seeder
{
    public function run(): void
    {
        // Certifique-se de que o ficheiro suppliers.csv está na raiz do projeto
        $csvFile = base_path('storage/app/suppliers.csv');

        if (!file_exists($csvFile)) {
            $this->command->error("⚠️ Arquivo suppliers.csv não encontrado na raiz do projeto!");
            return;
        }

        $file = fopen($csvFile, 'r');
        $header = fgetcsv($file); // Lê o cabeçalho
        $count = 0;

        $this->command->info("Iniciando a importação de Fornecedores...");

        while (($row = fgetcsv($file)) !== false) {
            // Evita linhas em branco ou mal formatadas
            if (count($header) !== count($row)) continue;
            
            $data = array_combine($header, $row);

            // Cria ou atualiza o fornecedor
            Supplier::updateOrCreate(
                ['id' => $data['id_fornecedor']], // Mantemos o ID original do seu sistema antigo
                [
                    'name' => $data['nome_fornecedor'] ?: 'Sem Nome',
                    'document' => $data['documento_cnpj_cpf'] ?: null,
                    'pix_key' => $data['chave_pix_principal'] ?: null,
                    'type' => $data['tipo_estabelecimento'] ?: 'physical',
                    'accounting_category' => $data['categoria_contabil'] ?: null,
                    'accounting_subcategory' => $data['subcategoria_contabil'] ?: null,
                    'is_active' => $data['ativo_na_lista_compras'] === '1',
                ]
            );
            $count++;
        }

        fclose($file);
        $this->command->info("✅ Sucesso! {$count} fornecedores foram importados e estão prontos a usar.");
    }
}