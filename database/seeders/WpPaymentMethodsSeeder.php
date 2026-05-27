<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\BankAccount;
use App\Models\PaymentMethod;

class WpPaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        BankAccount::unguard();
        PaymentMethod::unguard();

        // Limpeza de segurança
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('payment_methods')->truncate();
        DB::table('bank_accounts')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. CRIAR AS CONTAS BANCÁRIAS BASE
        $cofre = BankAccount::create([
            'name' => 'Cofre (Dinheiro em Espécie)',
            'type' => 'vault',
            'initial_balance' => 0,
            'is_active' => true,
        ]);

        $bancoPrincipal = BankAccount::create([
            'name' => 'Conta Corrente Principal',
            'type' => 'checking',
            'initial_balance' => 0,
            'is_active' => true,
        ]);

        // 2. IMPORTAR FORMAS DE PAGAMENTO
        $csvFile = base_path('storage/app/payment-methods.csv');
        
        if (!file_exists($csvFile)) {
            $this->command->error("Arquivo {$csvFile} não encontrado!");
            return;
        }

        $file = fopen($csvFile, 'r');
        fgetcsv($file); // Pula o cabeçalho
        $count = 0;

        while (($row = fgetcsv($file)) !== false) {
            if (!isset($row[0])) continue;

            $id = trim($row[0]);
            $nome = trim($row[1]);
            $categoria = trim($row[2]);
            $engine = trim($row[3]);
            $fiscal = trim($row[4]) === 'null' ? null : trim($row[4]);
            $pos = trim($row[5]) === '1';
            $delivery = trim($row[6]) === '1';
            $ativo = trim($row[7]) === '1';
            $icone = trim($row[8]);

            // INTELIGÊNCIA DE ROTEAMENTO
            $destinationId = null;
            
            if ($engine === 'money') {
                // Dinheiro vai para o Cofre
                $destinationId = $cofre->id;
            } elseif (in_array($engine, ['manual', 'pix_sicredi']) && !in_array($categoria, ['Perdas', 'null'])) {
                // Pix, Débito e Crédito vão para o Banco Principal
                $destinationId = $bancoPrincipal->id;
            }

            PaymentMethod::create([
                'id' => $id,
                'name' => $nome,
                'category' => $categoria === 'null' ? null : $categoria,
                'engine_type' => $engine,
                'fiscal_code' => $fiscal,
                'show_on_pos' => $pos,
                'show_on_delivery' => $delivery,
                'is_active' => $ativo,
                'icon' => $icone,
                'destination_account_id' => $destinationId,
            ]);
            
            $count++;
        }
        fclose($file);

        $this->command->info("==== SUCESSO ====");
        $this->command->line("- Contas Criadas: Cofre e Conta Principal");
        $this->command->line("- Formas de Pagamento importadas: {$count}");
    }
}