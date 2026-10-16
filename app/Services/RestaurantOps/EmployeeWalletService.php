<?php

namespace App\Services\RestaurantOps;

use App\Models\Employee;
use App\Models\CompanySetting;
use Exception;

class EmployeeWalletService
{
    public function getWalletDashboardData(int $employeeId): array
    {
        // Traz o funcionário e os últimos 50 movimentos da conta corrente
        $employee = Employee::with(['ledgers' => function($q) {
            $q->orderBy('created_at', 'desc')->limit(50);
        }])->findOrFail($employeeId);

        $settings = CompanySetting::getCached();

        // O saldo atual é sempre o 'balance' do último registro inserido
        $latestLedger = $employee->ledgers->first();
        $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0.00;

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'food_allowance_amount' => $employee->food_allowance_amount,
            ],
            'currentBalance' => $currentBalance,
            'ledgers' => $employee->ledgers,
            'settings' => [
                'has_food_allowance' => $settings->has_food_allowance ?? false,
                'self_service_enabled' => $settings->self_service_enabled ?? false,
                'allowance_frequency' => $settings->allowance_frequency ?? 'monthly',
                'allowance_scope' => $settings->allowance_scope ?? 'global',
            ]
        ];
    }
}
