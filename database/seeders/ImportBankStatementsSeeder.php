<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BankStatement;
use Illuminate\Support\Facades\DB;

class ImportBankStatementsSeeder extends Seeder
{
    public function run(): void
    {
        // Certifique-se de que o ficheiro conciliacao-bancaria.csv está na raiz do projeto
        $csvFile = base_path('storage/app/conciliacao-bancaria.csv');

        if (!file_exists($csvFile)) {
            $this->command->error("⚠️ Arquivo conciliacao-bancaria.csv não encontrado na raiz do projeto!");
            return;
        }

        $file = fopen($csvFile, 'r');
        $header = fgetcsv($file); // Lê o cabeçalho
        $count = 0;

        $this->command->info("Iniciando a importação do Histórico de Conciliação Bancária...");

        while (($row = fgetcsv($file)) !== false) {
            // Evita linhas em branco ou mal formatadas
            if (count($header) !== count($row)) continue;
            
            $data = array_combine($header, $row);

            // Se não tiver ID da transação, não conseguimos rastrear, então saltamos
            if (empty($data['id_transacao_banco'])) {
                continue;
            }

            $amount = (float) $data['valor_banco'];
            
            // Mapeia o status do seu sistema antigo para o novo
            $status = match($data['status_conciliacao']) {
                'Pendente no Sistema' => 'pending',
                'Conciliado' => 'reconciled',
                'Ignorado' => 'ignored',
                default => 'pending',
            };

            // Cria ou atualiza a linha do extrato
            BankStatement::updateOrCreate(
                ['fitid' => trim($data['id_transacao_banco'])], // O ID único que vem do banco
                [
                    'bank_date' => $data['data_no_banco'],
                    'description' => $data['historico_banco'],
                    'amount' => $amount,
                    'type' => $amount >= 0 ? 'credit' : 'debit',
                    'status' => $status,
                ]
            );
            $count++;
        }

        fclose($file);
        $this->command->info("✅ Mágica feita! {$count} linhas de extrato foram importadas para o painel.");
    }
}