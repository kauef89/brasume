<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Tenant;

class MapDatabasesCommand extends Command
{
    // O nome do comando que vai digitar no terminal
    protected $signature = 'erp:map-db {tenant_id=nativa : O ID do tenant para mapear junto com o central}';
    protected $description = 'Gera um mapa JSON unificado contendo as tabelas do Banco Central e de um Tenant específico.';

    public function handle()
    {
        $this->info("🔍 Iniciando mapeamento dos bancos de dados...");
        $map = [
            'central' => [],
            'tenant' => []
        ];

        // ==========================================
        // 1. MAPEAMENTO DO BANCO CENTRAL
        // ==========================================
        $this->info("⏳ Lendo arquitetura do Banco Central...");
        $map['central'] = $this->mapDatabaseTables();
        $this->line("   - " . count($map['central']) . " tabelas encontradas no Central.");

        // ==========================================
        // 2. MAPEAMENTO DO BANCO DO TENANT
        // ==========================================
        $tenantId = $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);

        if ($tenant) {
            // Entra no banco da lanchonete
            tenancy()->initialize($tenant);

            $this->info("⏳ Tenant '{$tenantId}' inicializado. Lendo arquitetura...");
            $map['tenant'] = $this->mapDatabaseTables();
            $this->line("   - " . count($map['tenant']) . " tabelas encontradas no Tenant.");

            // Volta para o banco central por segurança
            tenancy()->end();
        } else {
            $this->warn("⚠️ Aviso: Tenant '{$tenantId}' não encontrado. O mapa do tenant ficará vazio.");
        }

        // ==========================================
        // 3. EXPORTAÇÃO DO ARQUIVO JSON
        // ==========================================
        $jsonContent = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Salva na raiz da pasta storage para fácil acesso global
        $filePath = storage_path('app/mapa_bancos_erp.json');

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $jsonContent);

        $this->newLine();
        $this->info("✅ Mapa do banco unificado atualizado com sucesso!");
        $this->info("📂 Arquivo salvo em: {$filePath}");
    }

    /**
     * Função auxiliar que varre as tabelas da conexão ativa no momento
     */
    private function mapDatabaseTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $schema = [];

        foreach ($tables as $table) {
            $tableName = ((array)$table)[array_key_first((array)$table)];
            $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
            $schema[$tableName] = $columns;
        }

        return $schema;
    }
}
