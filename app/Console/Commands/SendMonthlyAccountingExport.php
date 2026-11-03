<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Services\Accounting\AccountingExportService;

class SendMonthlyAccountingExport extends Command
{
    protected $signature = 'accounting:send-monthly';
    protected $description = 'Gera e envia os fechamentos mensais (RH e Fiscal) para a contabilidade de todos os tenants.';

    public function handle()
    {
        $this->info('Iniciando o envio de fechamentos mensais...');

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->info("Processando tenant: {$tenant->id}");

            tenancy()->initialize($tenant);

            try {
                $service = new AccountingExportService();
                $service->sendMonthlyClosing();
                $this->info("✔ Fechamento enviado para {$tenant->id}.");
            } catch (\Exception $e) {
                $this->error("❌ Erro ao enviar fechamento de {$tenant->id}: " . $e->getMessage());
            }

            tenancy()->end();
        }

        $this->info('Processo concluído!');
    }
}
