<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\RecurringExpense;
use App\Models\AccountPayable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateRecurringPayables extends Command
{
    protected $signature = 'erp:generate-recurring-payables {--tenant= : ID do Tenant específico}';
    protected $description = 'Gera automaticamente os títulos no Contas a Pagar a partir das regras de despesas recorrentes.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId ? Tenant::where('id', $tenantId)->get() : Tenant::all();

        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $this->info("🔄 Processando despesas recorrentes do tenant: {$tenant->id}");

            $expenses = RecurringExpense::where('is_active', true)->get();
            $tenantCreated = 0;

            foreach ($expenses as $expense) {
                $dueDates = $this->calculateDueDatesForCurrentPeriod($expense);

                foreach ($dueDates as $dueDate) {
                    // Evita duplicidade para o mesmo título e mesma data de vencimento
                    $exists = AccountPayable::where('recurring_expense_id', $expense->id)
                        ->whereDate('due_date', $dueDate->toDateString())
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $amount = $expense->value_type === 'fixed'
                        ? (float) ($expense->default_amount ?? 0)
                        : (float) ($expense->default_amount ?? 0); // Estimativa se houver, ou 0.00

                    $description = "{$expense->name} - " . $dueDate->translatedFormat('F/Y');

                    AccountPayable::create([
                        'recurring_expense_id'   => $expense->id,
                        'supplier_id'            => $expense->supplier_id,
                        'employee_id'            => $expense->employee_id,
                        'description'            => $description,
                        'amount'                 => $amount,
                        'due_date'               => $dueDate->toDateString(),
                        'status'                 => 'pending',
                        'accounting_category'    => $expense->accounting_category,
                        'accounting_subcategory' => $expense->accounting_subcategory,
                        'is_personal'            => $expense->is_personal,
                        'notes'                  => "Gerado automaticamente por rotina recorrente (" . ($expense->frequency ?? 'monthly') . ").",
                    ]);

                    $expense->update(['last_generated_at' => now()]);
                    $tenantCreated++;
                    $totalCreated++;
                }
            }

            $this->info("✔ Tenant {$tenant->id}: {$tenantCreated} títulos gerados no Contas a Pagar.");
            tenancy()->end();
        }

        $this->info("🎉 Finalizado! {$totalCreated} novos títulos gerados no ecossistema.");
        Log::info("CRON [erp:generate-recurring-payables]: {$totalCreated} títulos gerados.");

        return Command::SUCCESS;
    }

    /**
     * Calcula as datas de vencimento aplicáveis para o ciclo corrente
     * @return Carbon[]
     */
    protected function calculateDueDatesForCurrentPeriod(RecurringExpense $expense): array
    {
        $dates = [];
        $today = Carbon::today();
        $frequency = $expense->frequency ?? 'monthly';

        switch ($frequency) {
            case 'weekly':
                // Gera para o dia da semana configurado na semana atual e na próxima
                $targetWeekday = (int) ($expense->due_weekday ?? 1); // Padrão: Segunda (1)
                $thisWeekDate = $today->copy()->startOfWeek()->addDays($targetWeekday);
                $nextWeekDate = $thisWeekDate->copy()->addWeek();

                if ($thisWeekDate->gte($today->copy()->subDays(2))) {
                    $dates[] = $thisWeekDate;
                }
                $dates[] = $nextWeekDate;
                break;

            case 'biweekly':
                // Parcela 1 no due_day; Parcela 2 com +15 dias
                $day1 = max(1, min(31, (int) ($expense->due_day ?? 1)));
                $date1 = $this->createSafeDate($today->year, $today->month, $day1);
                $date2 = $date1->copy()->addDays(15);

                $dates[] = $date1;
                $dates[] = $date2;
                break;

            case 'yearly':
                // Anual: Mês especificado e dia especificado
                $targetMonth = max(1, min(12, (int) ($expense->due_month ?? 1)));
                $targetDay = max(1, min(31, (int) ($expense->due_day ?? 1)));

                // Se o mês for o corrente ou próximo, gera
                $dates[] = $this->createSafeDate($today->year, $targetMonth, $targetDay);
                break;

            case 'monthly':
            default:
                // Gera o vencimento para o mês atual
                $targetDay = max(1, min(31, (int) ($expense->due_day ?? 1)));
                $dates[] = $this->createSafeDate($today->year, $today->month, $targetDay);

                // Se já estivermos no fim do mês (> dia 25), antecipa e gera o do próximo mês para projeção
                if ($today->day >= 25) {
                    $nextMonth = $today->copy()->addMonth();
                    $dates[] = $this->createSafeDate($nextMonth->year, $nextMonth->month, $targetDay);
                }
                break;
        }

        return $dates;
    }

    protected function createSafeDate(int $year, int $month, int $day): Carbon
    {
        $date = Carbon::createFromDate($year, $month, 1);
        $maxDays = $date->daysInMonth;
        return $date->setDay(min($day, $maxDays));
    }
}
