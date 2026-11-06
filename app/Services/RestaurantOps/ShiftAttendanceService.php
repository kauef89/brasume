<?php

namespace App\Services\RestaurantOps;

use App\Models\AccountPayable;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Order;
use App\Models\Payslip;
use App\Models\ShiftAttendance;
use App\Models\Transaction;
use App\Services\Financial\CashFlowProjectionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftAttendanceService
{
    public function __construct(protected CashFlowProjectionService $cashFlowService) {}

    public function syncShiftAttendees(array $attendees): void
    {
        $shift = CashShift::where('status', 'open')->latest('id')->first();
        if (!$shift) return;

        DB::transaction(function () use ($shift, $attendees) {
            $incomingIds = collect($attendees)->pluck('user_id')->toArray();

            // 1. Encerra presença de quem não está mais na lista
            ShiftAttendance::where('cash_shift_id', $shift->id)
                ->whereNull('clock_out')
                ->whereNotIn('user_id', $incomingIds)
                ->update(['clock_out' => now()]);

            // 2. Atualiza ou cria presenças
            foreach ($attendees as $data) {
                $status = $data['attendance_status'];
                $isAbsent = in_array($status, ['absent', 'day_off', 'vacation']);

                if ($isAbsent) {
                    ShiftAttendance::where('cash_shift_id', $shift->id)
                        ->where('user_id', $data['user_id'])
                        ->whereNull('clock_out')
                        ->update(['clock_out' => now(), 'attendance_status' => $status]);
                    continue;
                }

                ShiftAttendance::updateOrCreate(
                    [
                        'cash_shift_id' => $shift->id,
                        'user_id'       => $data['user_id'],
                        'clock_out'     => null,
                    ],
                    [
                        'clock_in'          => DB::raw('COALESCE(clock_in, NOW())'),
                        'attendance_status' => $status,
                        'daily_rate'        => $data['daily_rate'],
                        'assigned_role'     => $data['assigned_role'] ?? 'waiter',
                    ]
                );
            }
        });
    }

    public function getFreelancerPayoutDetails(int $userId): array
    {
        $shift = CashShift::where('status', 'open')->latest('id')->first();
        if (!$shift) {
            throw new \Exception("Nenhum turno aberto encontrado.");
        }

        $attendance = ShiftAttendance::where('cash_shift_id', $shift->id)
            ->where('user_id', $userId)
            ->first();

        $employee = Employee::where('user_id', $userId)->first() ?? Employee::find($userId);
        
        $grossRate = $attendance ? (float) $attendance->daily_rate : ($employee ? $employee->daily_rate : 0.0);
        $previousBalance = $employee ? (float) $employee->ledgers()->sum('amount') : 0.0;

        $consumptionOrders = Order::where('employee_id', $userId)
            ->where('type', 'counter')
            ->whereBetween('created_at', [$shift->opened_at, now()])
            ->get();

        $consumptions = $consumptionOrders->map(fn($o) => [
            'id'       => $o->id,
            'time'     => $o->created_at->format('H:i'),
            'title'    => "Comanda #{$o->id} - " . ($o->items->first()?->product_name ?? 'Consumo Local'),
            'amount'   => (float) $o->total,
            'category' => 'balcao',
        ])->toArray();

        $consumptionAmount = collect($consumptions)->sum('amount');
        $netAmount = max(0, $previousBalance + $grossRate - $consumptionAmount);

        $details = [
            'user'               => $employee ? $employee->toArray() : ['id' => $userId, 'name' => 'Colaborador'],
            'shift_date_label'   => $shift->opened_at->format('d/m/Y'),
            'gross_rate'         => $grossRate,
            'service_item'       => [
                'id'     => 'diaria_' . $shift->id,
                'title'  => 'Diária Fixa do Turno',
                'amount' => $grossRate,
            ],
            'previous_balance'   => $previousBalance,
            'consumption_amount' => $consumptionAmount,
            'consumption_details'=> ['items' => $consumptions],
            'net_amount'         => $netAmount,
            'ledger_history'     => $employee ? $employee->ledgers()->latest('id')->take(5)->get()->map(fn($l) => [
                'id'          => $l->id,
                'description' => $l->description,
                'amount'      => (float) $l->amount,
                'balance'     => (float) $l->balance,
                'type'        => $l->type,
                'created_at'  => $l->created_at->toIso8601String(),
            ])->toArray() : [],
        ];

        // 🚀 INJEÇÃO DO EXTRATO DE LOGÍSTICA (CORRIDAS DO MOTOBOY)
        if ($employee && $employee->is_courier) {
            $courierService = app(\App\Services\Orders\CourierSettlementService::class);
            $statement = $courierService->getCourierStatement($employee, $shift->opened_at, now());

            $details['courier_details'] = [
                'is_courier'           => true,
                'deliveries_count'     => $statement['deliveries_count'],
                'total_deliveries_fee' => $statement['total_deliveries_fee'],
                'daily_fee'            => $statement['daily_fee'],
                'minimum_guaranteed'   => $statement['minimum_guaranteed'],
                'final_payable'        => $statement['final_payable'],
                'cash_collected'       => $statement['cash_collected'],
                'net_cash_balance'     => $statement['net_cash_balance'],
            ];

            // Atualiza o total bruto (Diária + Produção de Corridas) para bater o acerto
            $details['gross_rate'] = $statement['final_payable'];
            $details['service_item']['amount'] = $statement['daily_fee'];
            $details['net_amount'] = max(0, $previousBalance + $statement['final_payable'] - $consumptionAmount);
        }

        return $details;
    }

    public function payFreelancer(array $data, $cashierUser): void
    {
        DB::transaction(function () use ($data, $cashierUser) {
            $shift = CashShift::where('status', 'open')->latest('id')->first();
            $employee = Employee::where('user_id', $data['user_id'])->first() ?? Employee::find($data['user_id']);
            
            if (!$shift || !$employee) {
                throw new \Exception("Turno ou colaborador não encontrado.");
            }

            $amount = (float) $data['amount'];
            $method = $data['payment_method'];

            // Se o atendente escolheu "Liquidar no Caixa" (Dinheiro ou Pix)
            if ($amount > 0 && in_array($method, ['cash', 'bank'])) {
                // Registra Saída no Caixa
                $transaction = Transaction::create([
                    'type'             => 'expense',
                    'category'         => 'payroll',
                    'amount'           => $amount,
                    'payment_method'   => $method,
                    'description'      => "Acerto de Diária/Corridas - {$employee->name}",
                    'bank_account_id'  => $data['bank_account_id'] ?? null,
                    'cash_shift_id'    => $method === 'cash' ? $shift->id : null,
                    'is_paid'          => true,
                    'paid_at'          => now(),
                    'user_id'          => $cashierUser->id,
                ]);

                // Marca presença como paga
                ShiftAttendance::where('cash_shift_id', $shift->id)
                    ->where('user_id', $data['user_id'])
                    ->update(['account_payable_id' => $transaction->id]);

                // Baixa as corridas do motoboy que estavam pendentes neste turno
                if ($employee->is_courier) {
                    Order::where('courier_id', $employee->id)
                        ->whereNull('courier_settled_at')
                        ->whereBetween('created_at', [$shift->opened_at, now()])
                        ->update(['courier_settled_at' => now()]);
                }
                
                // Zera o saldo do colaborador na conta corrente interna
                EmployeeLedger::create([
                    'employee_id' => $employee->id,
                    'type'        => 'debit',
                    'amount'      => $amount,
                    'description' => "Liquidação de Turno ({$method})",
                    'balance'     => 0.00,
                ]);
            } 
            // Se o atendente escolheu "Acumular no Saldo" (Rolar para amanhã/fim do mês)
            elseif ($method === 'accumulate' || $amount <= 0.00) {
                $gross = (float) ($data['gross_rate'] ?? 0);
                $consumed = (float) ($data['consumption_deducted'] ?? 0);
                
                $currentBalance = (float) $employee->ledgers()->sum('amount');
                $newBalance = $currentBalance + $gross - $consumed;

                if ($gross > 0) {
                    EmployeeLedger::create([
                        'employee_id' => $employee->id,
                        'type'        => 'credit',
                        'amount'      => $gross,
                        'description' => "Crédito Diária/Produção (Turno {$shift->opened_at->format('d/m')})",
                        'balance'     => $currentBalance + $gross,
                    ]);
                }

                if ($consumed > 0) {
                    EmployeeLedger::create([
                        'employee_id' => $employee->id,
                        'type'        => 'debit',
                        'amount'      => $consumed,
                        'description' => "Desconto Consumo Balcão",
                        'balance'     => $newBalance,
                    ]);
                }
            }
        });
    }
}
