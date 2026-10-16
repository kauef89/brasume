<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Jobs\SyncTenantSefazJob;

class SefazSync extends Command
{
    protected $signature = 'sefaz:sync {--force : Ignora a janela de cooldown interno}';
    protected $description = 'Executa a sincronização do DFe da SEFAZ para todas as empresas';

    public function handle()
    {
        $this->info("🚀 Sincronizando notas da SEFAZ para todas as empresas...");
        $force = (bool) $this->option('force');

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->line("⏳ Processando empresa: {$tenant->id}...");
            try {
                SyncTenantSefazJob::dispatchSync($tenant->id, $force);
                $this->info("✔️ Concluído com sucesso para: {$tenant->id}");
            } catch (\Throwable $e) {
                $this->error("❌ Falha na empresa {$tenant->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Todas as empresas foram processadas!");
    }
}
