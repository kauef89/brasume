<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Log;

class PruneOldPrintJobs extends Command
{
    protected $signature = 'erp:prune-print-jobs';
    protected $description = 'Remove trabalhos de impressão com mais de 7 dias para liberar espaço no banco de dados.';

    public function handle()
    {
        $tenants = Tenant::all();
        $totalDeleted = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $deleted = PrintJob::where('created_at', '<', now()->subDays(1))->delete();
            $totalDeleted += $deleted;

            tenancy()->end();
        }

        Log::info("🧹 [PruneOldPrintJobs] Limpeza concluída: {$totalDeleted} registros de impressão removidos.");
        $this->info("{$totalDeleted} print jobs removidos do banco.");
    }
}
