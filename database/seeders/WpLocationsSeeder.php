<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Neighborhood;
use App\Models\Street;

class WpLocationsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. IMPORTAR BAIRROS (Usando base_path para ignorar a pasta do Tenant)
        $bairrosCsv = base_path('storage/app/bairros.csv');
        
        if (file_exists($bairrosCsv)) {
            $file = fopen($bairrosCsv, 'r');
            fgetcsv($file); // Pula o cabeçalho
            
            while (($row = fgetcsv($file)) !== false) {
                // Índices: 0=id, 1=nome, 2=taxa, 3=frete_gratis
                $freteGratis = (isset($row[3]) && trim($row[3]) !== '') ? (float) $row[3] : null;

                Neighborhood::firstOrCreate(
                    ['name' => trim($row[1])], 
                    [
                        'delivery_fee' => (float) $row[2],
                        'free_shipping_over' => $freteGratis
                    ] 
                );
            }
            fclose($file);
            $this->command->info('Bairros importados com sucesso!');
        } else {
            $this->command->error('Ficheiro bairros.csv não encontrado no caminho: ' . $bairrosCsv);
        }

        // 2. IMPORTAR RUAS E CONECTAR AOS BAIRROS
        $ruasCsv = base_path('storage/app/ruas.csv');
        
        if (file_exists($ruasCsv)) {
            $file = fopen($ruasCsv, 'r');
            fgetcsv($file); // Pula o cabeçalho
            
            while (($row = fgetcsv($file)) !== false) {
                // Índices: 0=id, 1=nome, 2=bairro, 3=num_ini, 4=num_fim
                $nomeRua = trim($row[1]);
                $nomeBairro = trim($row[2]);
                $numInicial = (int) ($row[3] ?? 0);
                $numFinal = (isset($row[4]) && trim($row[4]) !== '') ? (int) $row[4] : null;

                $street = Street::firstOrCreate(
                    ['name' => $nomeRua]
                );

                $neighborhood = Neighborhood::where('name', $nomeBairro)->first();

                if ($neighborhood) {
                    $street->segments()->firstOrCreate([
                        'neighborhood_id' => $neighborhood->id,
                        'start_number' => $numInicial,
                        'end_number' => $numFinal,
                        'zip_code' => null,
                    ]);
                }
            }
            fclose($file);
            $this->command->info('Ruas e Faixas importadas com sucesso!');
        } else {
            $this->command->error('Ficheiro ruas.csv não encontrado no caminho: ' . $ruasCsv);
        }
    }
}