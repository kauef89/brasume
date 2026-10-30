<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyTenant extends Command
{
    protected $signature = 'erp:migrate-legacy {target_tenant_id}';
    protected $description = 'Migra todo o histórico de dados do banco de teste textual para o novo tenant estruturado com filtro dinâmico';

    public function handle()
    {
        $targetId = $this->argument('target_tenant_id');
        $newTenant = \App\Models\Tenant::find($targetId);

        if (!$newTenant) {
            $this->error("❌ Novo Tenant com o ID '{$targetId}' não foi localizado na central.");
            return Command::FAILURE;
        }

        $oldDatabase = 'tenant_nativa1'; // O banco legado atual
        $newDatabase = $newTenant->database()->getName(); // Descobre o nome do banco novo criado pelo Tenancy

        $this->info("🚀 Iniciando Migração Estrutural de Dados (Blindada):");
        $this->line(" ➡️  De: <fg=yellow>[{$oldDatabase}]</> para: <fg=green>[{$newDatabase}]</>");

        if (!$this->confirm('⚠️ Tem certeza que deseja rodar a migração? Certifique-se de que ninguém está operando o PDV agora.')) {
            $this->line('Migração abortada.');
            return Command::INVALID;
        }

        // Tabelas na ordem correta de dependência (chaves estrangeiras)
        $tables = [
            'users', 'bank_accounts', 'categories', 'products', 'modifier_groups',
            'modifier_options', 'product_modifiers', 'dining_tables', 'customers',
            'addresses', 'orders', 'order_items', 'order_item_modifiers',
            'order_payments', 'company_settings', 'recurring_expenses',
            'account_payables', 'account_receivables', 'transactions', 'bank_statements'
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $summary = []; // Array para guardar os resultados e cuspir no final
        $totalRecords = 0;

        $this->output->progressStart(count($tables));

        foreach ($tables as $table) {
            try {
                // 1. Limpa os dados padrões (se houver) do banco de destino
                DB::table("{$newDatabase}.{$table}")->truncate();

                // 🚀 O SEGREDO ESTÁ AQUI: Descobre quais colunas existem de verdade na tabela NOVA
                $validColumns = collect(DB::select("SHOW COLUMNS FROM `{$newDatabase}`.`{$table}`"))
                                    ->pluck('Field')
                                    ->toArray();

                // Transforma os valores em chaves para o array_intersect_key funcionar perfeitamente
                $validColumnsKeys = array_flip($validColumns);

                // 2. Copia todo o histórico mantendo os IDs originais
                $rows = DB::table("{$oldDatabase}.{$table}")->get();
                $count = count($rows);
                $insertBatch = [];

                foreach ($rows as $row) {
                    $rowArray = (array) $row;

                    // 🚀 FILTRO DINÂMICO: Remove do array qualquer coluna velha que não exista no banco novo
                    $filteredRow = array_intersect_key($rowArray, $validColumnsKeys);

                    $insertBatch[] = $filteredRow;

                    // 🚀 INSERÇÃO EM LOTE: Salva a cada 500 registros para economizar memória e tempo
                    if (count($insertBatch) >= 500) {
                        DB::table("{$newDatabase}.{$table}")->insert($insertBatch);
                        $insertBatch = []; // Limpa o lote
                    }
                }

                // Insere os "pingados" finais que sobraram no último lote
                if (!empty($insertBatch)) {
                    DB::table("{$newDatabase}.{$table}")->insert($insertBatch);
                }

                $summary[] = [$table, $count, '<fg=green>✅ Sucesso</>'];
                $totalRecords += $count;

            } catch (\Exception $e) {
                $summary[] = [$table, 0, '<fg=red>❌ Erro</>'];
                $this->error("\nErro na tabela {$table}: " . $e->getMessage());
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ==========================================
        // O CUSPE NA TELA (Resumo da Ópera)
        // ==========================================
        $this->newLine();
        $this->info("📊 RESUMO DA MIGRAÇÃO");
        $this->table(
            ['Tabela', 'Registros Cópiados', 'Status'],
            $summary
        );

        $this->newLine();
        $this->info("✨ MIGRAÇÃO CONCLUÍDA COM FILTRO DINÂMICO! ✨");
        $this->line("<fg=cyan>Total de registros salvos:</> {$totalRecords}");
        $this->line("<fg=yellow>Lembrete:</> Mova os arquivos de 'storage/tenants/nativa' para 'storage/tenants/{$targetId}' na sua VPS.");
        $this->newLine();

        return Command::SUCCESS;
    }
}
