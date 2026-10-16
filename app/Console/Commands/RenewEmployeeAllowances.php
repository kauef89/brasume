<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\CompanySetting;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;

class RenewEmployeeAllowances extends Command
{
    protected $signature = 'erp:renew-allowances';
    protected $description = 'Renova o saldo de vale-alimentação dos colaboradores (Diário ou Mensal) de forma automática.';

    public function handle()
    {
        $this->info("🚀 Iniciando motor de renovação de Vales (Multi-Tenant)...");

        // Trazemos todos os Tenants para varrer um por um
        $tenants = Tenant::all();
        $totalWalletsUpdated = 0;

        foreach ($tenants as $tenant) {
            // Isola o banco de dados para o cliente atual
            tenancy()->initialize($tenant);

            $settings = CompanySetting::getCached();

            // Se este cliente não usa o vale-alimentação, pula para o próximo!
            if (!$settings || !$settings->has_food_allowance) {
                tenancy()->end();
                continue;
            }

            // 🚀 INTELIGÊNCIA TEMPORAL
            // Se a regra for MENSAL e hoje não for dia 1º, ignoramos este tenant.
            if ($settings->allowance_frequency === 'monthly' && now()->day !== 1) {
                tenancy()->end();
                continue;
            }

            $employees = Employee::where('is_active', true)->get();
            $tenantUpdates = 0;

            foreach ($employees as $employee) {
                // Descobre de onde vem o valor (Global vs Individual)
                $amount = $settings->allowance_scope === 'global'
                    ? (float) $settings->global_allowance_amount
                    : (float) $employee->food_allowance_amount;

                // Só processa se houver algum valor configurado
                if ($amount > 0) {
                    $latestLedger = $employee->ledgers()->latest('id')->first();
                    $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;

                    $desc = $settings->allowance_frequency === 'daily'
                        ? 'Renovação Diária do Vale-Alimentação'
                        : 'Renovação Mensal do Vale-Alimentação';

                    // Registra o depósito no extrato do funcionário
                    $employee->ledgers()->create([
                        'type' => 'credit',
                        'description' => $desc,
                        'amount' => $amount,
                        'balance' => $currentBalance + $amount,
                    ]);

                    $tenantUpdates++;
                    $totalWalletsUpdated++;
                }
            }

            $this->info("✅ Tenant {$tenant->id}: {$tenantUpdates} carteiras renovadas.");

            // Fecha a conexão com o tenant atual para não vazar dados
            tenancy()->end();
        }

        $this->info("🎉 Processo concluído! {$totalWalletsUpdated} carteiras atualizadas em todo o ecossistema.");
        Log::info("CRON [erp:renew-allowances] executado: {$totalWalletsUpdated} carteiras renovadas.");

        return Command::SUCCESS;
    }
}
