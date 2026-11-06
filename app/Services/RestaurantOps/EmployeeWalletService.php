<?php

namespace App\Services\RestaurantOps;

use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Order;

class EmployeeWalletService
{
    public function getWalletDashboardData(int $employeeId): array
    {
        $employee = Employee::with(['ledgers' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(50);
        }])->findOrFail($employeeId);

        $settings = CompanySetting::getCached();

        // 1. Saldo Financeiro Real da Conta-Corrente (Diárias a receber vs Dívidas de fiado)
        $latestLedger = $employee->ledgers->first();
        $financialBalance = $latestLedger ? (float) $latestLedger->balance : 0.00;

        // 2. Cálculo da Cota de Refeição Disponível Hoje
        $dailyAllowanceLimit = 0.00;
        $dailyAllowanceConsumed = 0.00;

        if ($settings && $settings->has_food_allowance) {
            $dailyAllowanceLimit = $settings->allowance_scope === 'global'
                ? (float) ($settings->global_allowance_amount ?? 0)
                : (float) ($employee->meal_allowance ?? $employee->food_allowance_amount ?? 0);

            // Se for Não-Cumulativo, calcula quanto o colaborador já utilizou no turno de hoje
            if (($settings->carry_over_mode ?? 'none') === 'none') {
                $activeShift = CashShift::where('status', 'open')->latest('opened_at')->first();
                if ($activeShift) {
                    $shiftOrders = Order::where('cash_shift_id', $activeShift->id)
                        ->where('source', 'team_app')
                        ->whereNotIn('status', ['cancelled'])
                        ->where(function ($q) use ($employee) {
                            $q->where('employee_id', $employee->id)
                              ->orWhere('customer_id', $employee->customer?->id);
                        })
                        ->get();

                    $dailyAllowanceConsumed = (float) $shiftOrders->sum('total');
                }
            }
        }

        $dailyAllowanceRemaining = max(0.00, round($dailyAllowanceLimit - $dailyAllowanceConsumed, 2));

        return [
            'employee' => [
                'id'                    => $employee->id,
                'name'                  => $employee->name,
                'food_allowance_amount' => $employee->food_allowance_amount,
            ],
            'currentBalance'          => $financialBalance,           // Saldo financeiro em folha
            'dailyAllowanceLimit'     => $dailyAllowanceLimit,        // Teto de lanche do dia
            'dailyAllowanceRemaining' => $dailyAllowanceRemaining,    // Quanto ainda pode comer hoje de graça
            'ledgers'                 => $employee->ledgers,
            'settings'                => [
                'has_food_allowance'   => (bool) ($settings->has_food_allowance ?? false),
                'self_service_enabled' => (bool) ($settings->self_service_enabled ?? false),
                'allowance_frequency'  => $settings->allowance_frequency ?? 'daily',
                'allowance_scope'      => $settings->allowance_scope ?? 'global',
                'carry_over_mode'      => $settings->carry_over_mode ?? 'none',
            ],
        ];
    }
}
