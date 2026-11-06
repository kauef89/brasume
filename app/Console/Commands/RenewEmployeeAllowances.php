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
    protected $description = 'Gerencia a renovação e expiração do saldo de vale-alimentação dos colaboradores.';

    public function handle(): int
    {
        $this->info("🚀 Iniciando motor de renovação de Vales (Multi-Tenant)...");

        $tenants = Tenant::all();
        $totalWalletsUpdated = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $settings = CompanySetting::getCached();

            // Se o tenant não oferece vale-alimentação, avança
            if (!$settings || !$settings->has_food_allowance) {
                tenancy()->end();
                continue;
            }

            $carryOverMode = $settings->carry_over_mode ?? 'none';

            // 🛡️ REGRA 1: NÃO-CUMULATIVO (Expira diariamente / por período)
            // No modo não-cumulativo, o vale NÃO vira dinheiro na conta-corrente.
            // O colaborador tem a cota garantida para consumo no turno via MealAllowanceService.
            if ($carryOverMode === 'none') {
                $this->info("ℹ️ Tenant {$tenant->id}: Modo Não-Cumulativo ativo. Cotas diárias operam por turno (sem depósito financeiro em conta-corrente).");
                tenancy()->end();
                continue;
            }

            // 🛡️ REGRA 2: CUMULATIVO (Acumula saldo financeiro na carteira corporativa)
            if ($settings->allowance_frequency === 'monthly' && now()->day !== 1) {
                tenancy()->end();
                continue;
            }

            $employees = Employee::where('is_active', true)->get();
            $tenantUpdates = 0;

            foreach ($employees as $employee) {
                $amount = $settings->allowance_scope === 'global'
                    ? (float) ($settings->global_allowance_amount ?? 0)
                    : (float) ($employee->food_allowance_amount ?? 0);

                if ($amount > 0) {
                    $latestLedger = $employee->ledgers()->latest('id')->first();
                    $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0.00;

                    $desc = $settings->allowance_frequency === 'daily'
                        ? 'Crédito Cumulativo de Vale-Alimentação (Diário)'
                        : 'Crédito Cumulativo de Vale-Alimentação (Mensal)';

                    $employee->ledgers()->create([
                        'type'        => 'credit',
                        'description' => $desc,
                        'amount'      => $amount,
                        'balance'     => $currentBalance + $amount,
                    ]);

                    $tenantUpdates++;
                    $totalWalletsUpdated++;
                }
            }

            $this->info("✅ Tenant {$tenant->id}: {$tenantUpdates} carteiras cumulativas creditadas.");
            tenancy()->end();
        }

        $this->info("🎉 Processo concluído! {$totalWalletsUpdated} carteiras processadas.");
        Log::info("CRON [erp:renew-allowances] executado: {$totalWalletsUpdated} carteiras processadas.");

        return Command::SUCCESS;
    }
}
