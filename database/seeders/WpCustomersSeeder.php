<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Address;
use App\Models\Street;
use App\Models\Neighborhood;

class WpCustomersSeeder extends Seeder
{
    public function run(): void
    {
        $csvFile = base_path('storage/app/clientes.csv');
        
        if (!file_exists($csvFile)) {
            $this->command->error("Ficheiro não encontrado no caminho: {$csvFile}");
            return;
        }

        $file = fopen($csvFile, 'r');
        fgetcsv($file); // Pula o cabeçalho
        
        $count = 0;

        while (($row = fgetcsv($file)) !== false) {
            // Índices do CSV:
            // 0:id, 1:nome, 2:email, 3:telefone, 4:cpf, 5:data_nascimento, 6:wc_rua, 7:wc_numero, 8:wc_bairro, 9:enderecos_app

            // 1. LIMPEZA DOS DADOS PESSOAIS
            $nome = trim($row[1]);
            $email = (strtolower(trim($row[2])) === 'null' || empty(trim($row[2]))) ? null : trim($row[2]);
            $telefone = trim($row[3]);
            $cpf = trim($row[4]);
            $dataNasc = (strtolower(trim($row[5])) === 'null' || empty(trim($row[5]))) ? null : trim($row[5]);

            $cpf = preg_replace('/[^0-9]/', '', $cpf);
            $telefone = preg_replace('/[^0-9]/', '', $telefone);

            if (empty($cpf)) continue; 

            // 2. SALVAR O CLIENTE
            $customer = Customer::updateOrCreate(
                ['cpf' => $cpf],
                [
                    'name' => $nome,
                    'email' => $email,
                    'phone' => $telefone,
                    'birth_date' => $dataNasc ?? '1900-01-01',
                ]
            );

            $enderecosParaSalvar = [];

            // 3. RECUPERAR ENDEREÇOS PADRÃO (WooCommerce)
            $wcRua = trim($row[6]);
            if (strtolower($wcRua) !== 'null' && !empty($wcRua)) {
                $wcNumero = trim($row[7]);
                $isSemNumero = empty($wcNumero) || strtoupper($wcNumero) === 'S/N';

                $enderecosParaSalvar[] = [
                    'nickname' => 'Casa', // O padrão para o WC é Casa
                    'street' => $wcRua,
                    'number' => $isSemNumero ? 'S/N' : $wcNumero,
                    'no_number' => $isSemNumero,
                    'district' => trim($row[8]),
                    'complement' => null,
                    'reference' => 'Site WooCommerce',
                    'location_link' => null,
                ];
            }

            // 4. RECUPERAR ENDEREÇOS DO APP (Array Serializado)
            $serializado = trim($row[9]);
            if (strtolower($serializado) !== 'null' && !empty($serializado)) {
                $enderecosApp = @unserialize($serializado); 
                
                if (is_array($enderecosApp)) {
                    foreach ($enderecosApp as $end) {
                        $numeroApp = trim($end['number'] ?? '');
                        $isSemNumeroApp = empty($numeroApp) || strtoupper($numeroApp) === 'S/N';
                        
                        // Garante que o nickname seja um dos aceites (senão, cai no "Outro")
                        $rawNickname = ucfirst(strtolower(trim($end['nickname'] ?? 'Casa')));
                        $validNicknames = ['Casa', 'Trabalho', 'Amor', 'Pai/Mãe', 'Outro'];
                        $nicknameFinal = in_array($rawNickname, $validNicknames) ? $rawNickname : 'Outro';

                        $enderecosParaSalvar[] = [
                            'nickname' => $nicknameFinal,
                            'street' => $end['street'] ?? '',
                            'number' => $isSemNumeroApp ? 'S/N' : $numeroApp,
                            'no_number' => $isSemNumeroApp,
                            'district' => $end['district'] ?? '',
                            'complement' => $end['complement'] ?? null,
                            'reference' => $end['reference'] ?? null,
                            'location_link' => $end['location_link'] ?? null, // Se existia link no WP, resgatamos!
                        ];
                    }
                }
            }

            // 5. SALVAR OS ENDEREÇOS NO BANCO DE DADOS
            foreach ($enderecosParaSalvar as $endData) {
                if (empty($endData['street']) || empty($endData['district'])) continue;

                $street = Street::firstOrCreate(['name' => $endData['street']]);
                
                $neighborhood = Neighborhood::firstOrCreate(
                    ['name' => $endData['district']],
                    ['delivery_fee' => 0]
                );

                Address::firstOrCreate([
                    'customer_id' => $customer->id,
                    'street_id' => $street->id,
                    'neighborhood_id' => $neighborhood->id,
                    'number' => $endData['number'],
                    'nickname' => $endData['nickname'],
                ], [
                    'no_number' => $endData['no_number'],
                    'complement' => $endData['complement'],
                    'reference' => $endData['reference'],
                    'location_link' => $endData['location_link'],
                ]);
            }
            $count++;
        }
        
        fclose($file);
        $this->command->info("Fantástico! {$count} Clientes com os seus endereços otimizados (Apelidos e GPS) foram importados!");
    }
}