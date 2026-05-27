<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ModifierOption;
use Illuminate\Support\Facades\File;

class ProcessPredictiveWeights extends Command
{
    // O comando exige o caminho do arquivo JSON
    protected $signature = 'app:process-predictive-weights {filepath}';
    protected $description = 'Lê um JSON de histórico de vendas e atualiza o peso preditivo (BI) dos modificadores.';

    public function handle()
    {
        $filepath = $this->argument('filepath');

        if (!File::exists($filepath)) {
            $this->error("Arquivo não encontrado: {$filepath}");
            return Command::FAILURE;
        }

        $this->info("Lendo JSON...");
        $json = File::get($filepath);
        $data = json_decode($json, true);

        if (!$data) {
            $this->error("O arquivo JSON é inválido ou está vazio.");
            return Command::FAILURE;
        }

        $optionCounts = [];
        $totalPedidos = count($data);
        $this->info("Processando {$totalPedidos} itens de histórico...");

        // 1. Agrega as quantidades
        foreach ($data as $item) {
            // Se quiser filtrar apenas pedidos concluídos no futuro, coloque um IF aqui.
            // if ($item['status_do_item'] !== 'completed') continue;

            if (!isset($item['adicionais_escolhidos']) || !is_array($item['adicionais_escolhidos'])) {
                continue;
            }

            foreach ($item['adicionais_escolhidos'] as $adicional) {
                $name = trim($adicional['nome']);
                $qtd = (int) ($adicional['qtd'] ?? 1);

                if (!isset($optionCounts[$name])) {
                    $optionCounts[$name] = 0;
                }
                $optionCounts[$name] += $qtd;
            }
        }

        // 2. Atualiza o banco de dados
        $updatedCount = 0;
        $this->info("Sincronizando pesos com o banco de dados...");

        foreach ($optionCounts as $name => $count) {
            // Busca opções com esse nome (independente de maiúsculas/minúsculas) e atualiza o peso
            $updated = ModifierOption::where('name', 'LIKE', $name)->update([
                'historical_weight' => $count
            ]);

            if ($updated > 0) {
                $this->line("<fg=green>✓</> '{$name}' -> Peso atualizado para: {$count}");
                $updatedCount++;
            } else {
                $this->line("<fg=red>✗</> '{$name}' -> Ignorado (Não encontrado no painel atual)");
            }
        }

        $this->info("Concluído! {$updatedCount} modificadores tiveram seu BI atualizado.");
        return Command::SUCCESS;
    }
}
