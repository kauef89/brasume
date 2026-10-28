<?php

namespace App\Services\RestaurantOps;

use App\Enums\AccountingCategory;

use App\Enums\EmploymentType;
use App\Enums\PaymentCycle;
use App\Models\User;
use App\Models\Employee;
use App\Models\CashShift;
use App\Models\Order;
use App\Models\BankAccount;
use App\Models\AccountPayable;
use App\Models\Transaction;
use App\Models\Printer;
use App\Services\Printing\Documents\FreelancerReceipt;
use App\Services\Printing\PrintDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftAttendanceService
{
    public function __construct(
        protected PrintDispatcher $printDispatcher
    ) {}

    public function getShiftUsersData(): array
    {
        $hojeInt = now()->dayOfWeek;
        $mapaDias = [
            0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua',
            4 => 'qui', 5 => 'sex', 6 => 'sab'
        ];
        $hojeString = $mapaDias[$hojeInt];

        $roleMap = [
            'admin' => 'Administrador', 'manager' => 'Gerente',
            'pos' => 'Caixa', 'waiter' => 'Garçom',
            'driver' => 'Motoboy', 'kitchen' => 'Cozinha'
        ];

        return User::with(['employee', 'roles'])
            ->where('is_active', true)
            ->whereHas('employee', function ($query) {
                $query->where('is_active', true);
            })
            ->get()
            ->map(function ($u) use ($hojeString, $roleMap) {
                $emp = $u->employee;
                $isScheduled = false;
                $dailyRate = 0;
                $isFreelancer = false;

                if ($emp) {
                    $dailyRate = (float) ($emp->base_remuneration ?? 0);

                    $isFreelancer = in_array($emp->employment_type, [
                        EmploymentType::FREELANCE_FIXED,
                        EmploymentType::FREELANCER,
                    ], true);

                    if (!empty($emp->work_days)) {
                        $workDays = is_string($emp->work_days) ? json_decode($emp->work_days, true) : $emp->work_days;
                        $isScheduled = is_array($workDays) && in_array($hojeString, $workDays);
                    }
                }

                $availableRoles = $u->roles->map(function ($role) use ($roleMap) {
                    return [
                        'value' => $role->name,
                        'label' => $roleMap[$role->name] ?? ucfirst($role->name)
                    ];
                })->toArray();

                if (empty($availableRoles)) {
                    $availableRoles = [['value' => 'waiter', 'label' => 'Garçom']];
                }

                return [
                    'id'              => $u->id,
                    'user_id'         => $u->id,
                    'name'            => $u->name,
                    'available_roles' => $availableRoles,
                    'employment_type' => $isFreelancer ? 'freelancer' : 'clt',
                    'is_scheduled'    => $isScheduled,
                    'default_rate'    => $dailyRate,
                    'daily_rate'      => $dailyRate,
                ];
            })
            ->sortBy('name')
            ->values()
            ->toArray();
    }

    public function getCurrentAttendees(?CashShift $currentShift): array
    {
        if (!$currentShift) return [];

        return $currentShift->attendees->map(function($a) {
            return [
                'user_id'            => $a->id,
                'status'             => $a->pivot->status,
                'attendance_status'  => $a->pivot->attendance_status,
                'daily_rate'         => (float) ($a->pivot->daily_rate ?? 0),
                'account_payable_id' => $a->pivot->account_payable_id,
                'assigned_role'      => $a->pivot->assigned_role ?? null
            ];
        })->toArray();
    }

    public function syncShiftAttendees(array $attendees, ?CashShift $shift = null): void
    {
        $shift = $shift ?? CashShift::current();
        if (!$shift) return;

        $currentAttendees = $shift->attendees()->get()->keyBy('id');

        $syncData = [];
        foreach ($attendees as $att) {
            if (is_numeric($att)) {
                $userId = (int) $att;
                $att = ['user_id' => $userId];
            } else {
                $userId = $att['user_id'] ?? $att['id'] ?? null;
            }
            if (!$userId) continue;

            $existing = $currentAttendees->get($userId);

            $dailyRate = (float) (
                $att['daily_rate']
                ?? $att['daily.rate']
                ?? $att['dailyRate']
                ?? $att['default_rate']
                ?? data_get($att, 'daily.rate')
                ?? ($existing ? $existing->pivot->daily_rate : 0)
            );

            $syncData[$userId] = [
                'checked_in_at'     => $existing ? $existing->pivot->checked_in_at : now(),
                'status'            => 'active',
                'attendance_status' => $att['attendance_status'] ?? $att['status'] ?? 'present',
                'daily_rate'        => $dailyRate,
                'assigned_role'     => $att['assigned_role'] ?? ($existing ? $existing->pivot->assigned_role : null)
            ];
        }

        if (!isset($syncData[$shift->opened_by])) {
            $existingManager = $currentAttendees->get($shift->opened_by);
            $syncData[$shift->opened_by] = [
                'checked_in_at'     => $existingManager ? $existingManager->pivot->checked_in_at : $shift->opened_at,
                'status'            => 'active',
                'attendance_status' => 'present',
                'daily_rate'        => $existingManager ? (float) ($existingManager->pivot->daily_rate ?? 0) : 0,
                'assigned_role'     => $existingManager ? ($existingManager->pivot->assigned_role ?? 'manager') : 'manager'
            ];
        }

        $shift->attendees()->syncWithoutDetaching($syncData);

        $receivedUserIds = array_keys($syncData);
        $missingIds = $currentAttendees->keys()->diff($receivedUserIds);

        if ($missingIds->isNotEmpty()) {
            foreach ($missingIds as $missingId) {
                $shift->attendees()->updateExistingPivot($missingId, [
                    'status'         => 'inactive',
                    'checked_out_at' => now()
                ]);
            }
        }
    }

    /**
     * 🚀 PONTE DE CONSUMO: Calcula o consumo do colaborador durante o turno aberto
     */
    public function getFreelancerShiftConsumption(User $user, CashShift $shift): array
    {
        $employee = $user->employee;
        $shiftStart = $shift->opened_at;

        // 1. Localiza o cliente vinculado ao usuário ou colaborador
        $customer = $user->customer;
        if (!$customer && $employee) {
            $customer = \App\Models\Customer::where('phone', $employee->phone)
                ->orWhere('cpf', $employee->cpf)
                ->first();
        }

        // 2. Busca pedidos com consumo interno ou vinculados ao perfil
        $orders = Order::where('created_at', '>=', $shiftStart)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($user, $customer) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('employee_id', $user->id)
                        ->where(function ($w) {
                            $w->where('customer_name', 'like', '%Consumo Interno%')
                              ->orWhere('payment_intent_status', 'internal')
                              ->orWhere('payment_intent_methods', 'like', '%internal%');
                        });
                });

                if ($customer) {
                    $q->orWhere('customer_id', $customer->id);
                }
            })
            ->get();

        $ordersTotal = (float) $orders->sum('total');

        // 3. Débitos avulsos na conta corrente durante o turno
        $ledgerDebits = 0.0;
        if ($employee) {
            $ledgerDebits = (float) $employee->ledgers()
                ->where('created_at', '>=', $shiftStart)
                ->where('type', 'debit')
                ->where('description', 'like', '%Consumo%')
                ->sum('amount');
        }

        $totalConsumption = round($ordersTotal + $ledgerDebits, 2);

        return [
            'total'          => $totalConsumption,
            'orders_count'   => $orders->count(),
            'orders'         => $orders->map(fn($o) => [
                'id'    => $o->id,
                'total' => (float) $o->total,
                'time'  => $o->created_at->format('H:i')
            ])->toArray(),
            'ledger_debits'  => $ledgerDebits,
        ];
    }

    /**
     * Retorna a radiografia financeira completa para o modal de liquidação
     */
    public function getFreelancerPayoutDetails(int $userId, ?CashShift $shift = null): array
    {
        $shift = $shift ?? CashShift::where('status', 'open')->latest()->first();
        if (!$shift) {
            throw new \Exception('Nenhum turno aberto.');
        }

        $user = User::with('employee')->findOrFail($userId);
        $employee = $user->employee;

        $attendeePivot = $shift->attendees()->where('users.id', $userId)->first()?->pivot;
        $grossRate = (float) ($attendeePivot?->daily_rate ?: ($employee?->base_remuneration ?? 0));

        $consumptionData = $this->getFreelancerShiftConsumption($user, $shift);
        $consumptionAmount = (float) $consumptionData['total'];

        $netAmount = max(0, round($grossRate - $consumptionAmount, 2));

        $latestLedger = $employee?->ledgers()->latest('id')->first();
        $previousBalance = $latestLedger ? (float) $latestLedger->balance : 0.00;
        $totalDue = round($previousBalance + $netAmount, 2);

        return [
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'pix_key_type' => $employee?->pix_key_type,
                'pix_key'      => $employee?->pix_key,
            ],
            'previous_balance'    => $previousBalance,
            'ledger_history'      => $employee ? $employee->ledgers()->latest('id')->take(30)->get()->toArray() : [],
            'gross_rate'          => $grossRate,
            'consumption_amount'  => $consumptionAmount,
            'consumption_details' => $consumptionData,
            'net_amount'          => $netAmount, // Líquido SÓ de hoje
            'total_due'           => $totalDue,  // Saldo + Hoje
            'is_paid'             => (bool) ($attendeePivot?->account_payable_id),
        ];
    }

    /**
     * 🚀 Liquidação oficial do freelancer com saída bancária/caixa, extrato de RH e impressão
     */
    public function payFreelancer(array $data, User $operator): void
    {
        $user = User::with('employee')->findOrFail($data['user_id']);
        $employee = $user->employee;

        if (!$employee) {
            throw new \Exception('O colaborador não tem um perfil de RH vinculado.');
        }

        $shift = CashShift::where('status', 'open')->latest()->first();
        if (!$shift) {
            throw new \Exception('Não existe nenhum turno aberto para registrar o pagamento.');
        }

        $payoutDetails = $this->getFreelancerPayoutDetails($user->id, $shift);
        $grossRate = (float) ($data['gross_rate'] ?? $payoutDetails['gross_rate']);
        $consumptionAmount = (float) ($data['consumption_deducted'] ?? $payoutDetails['consumption_amount']);
        $netAmount = (float) max(0, round($grossRate - $consumptionAmount, 2)); // O que ele gerou hoje

        // O valor efetivamente pago pelo operador (Pode ser maior, menor ou igual ao total_due)
        $paidAmount = isset($data['amount']) ? (float) $data['amount'] : (float) ($payoutDetails['total_due'] ?? $netAmount);

        $paymentMethod = $data['payment_method']; // 'cash' ou 'bank'
        $bankAccountId = !empty($data['bank_account_id']) ? (int) $data['bank_account_id'] : null;

        $bankAccount = $bankAccountId ? BankAccount::find($bankAccountId) : null;
        $paymentMethodLabel = $paymentMethod === 'accumulate' ? 'Acumulado no Saldo (Sem repasse)' : ($paymentMethod === 'cash' ? 'Dinheiro (Gaveta)' : 'PIX (' . ($bankAccount?->name ?? 'Banco') . ')');

        DB::transaction(function () use (
            $data, $user, $employee, $shift, $operator,
            $grossRate, $consumptionAmount, $netAmount, $paidAmount,
            $paymentMethod, $bankAccountId, $paymentMethodLabel
        ) {
            $desc = "Diária Turno #{$shift->id} - {$user->name}";
            if ($consumptionAmount > 0) {
                $desc .= " (Bruto: R$ {$grossRate} - Consumo: R$ {$consumptionAmount})";
            }
            if ($paidAmount <= 0) {
                $desc = "Acúmulo de Saldo (Fechamento de Turno #{$shift->id}) - {$user->name}";
            }

            // 1. Cria o Contas a Pagar já liquidado (a baixa bancária/gaveta é registrada na tabela transactions)
            $payable = AccountPayable::create([
                'employee_id'            => $employee->id,
                'description'            => $desc,
                'amount'                 => $paidAmount,
                'due_date'               => now()->toDateString(),
                'payment_date'           => now()->toDateString(),
                'status'                 => 'paid',
                'accounting_category'    => AccountingCategory::PAYROLL->value,
                'accounting_subcategory' => 'freelancers',
                'paid_by'                => 'company',
                'observations'           => "Liquidado via {$paymentMethodLabel} no Turno #{$shift->id}",
            ]);

            // 2. Sangria no Caixa se pago em dinheiro da gaveta
            if ($paymentMethod === 'cash' && $paidAmount > 0) {
                Transaction::create([
                    'cash_shift_id'   => $shift->id,
                    'bank_account_id' => $shift->bank_account_id,
                    'type'            => 'out',
                    'category'        => 'cash_out',
                    'amount'                 => $paidAmount,
                    'description'     => "Sangria: Diária Freelancer - {$user->name}",
                    'operator_name'   => $operator->name ?? 'Sistema',
                    'transaction_date'=> now(),
                ]);
            }

            // 3. Transação bancária se pago via PIX/Banco fora da gaveta
            if ($paymentMethod === 'bank' && $bankAccountId && $paidAmount > 0) {
                Transaction::create([
                    'cash_shift_id'   => null,
                    'bank_account_id' => $bankAccountId,
                    'type'            => 'out',
                    'category'        => 'bank_out',
                    'amount'                 => $paidAmount,
                    'description'     => "PIX Diária Freelancer - {$user->name}",
                    'operator_name'   => $operator->name ?? 'Sistema',
                    'transaction_date'=> now(),
                ]);
            }

            // 4. Conciliação completa na conta-corrente do colaborador (EmployeeLedger)
            $latestLedger = $employee->ledgers()->latest('id')->first();
            $balance = $latestLedger ? (float) $latestLedger->balance : 0.00;

            // Crédito da diária
            $employee->ledgers()->create([
                'type'        => 'credit',
                'description' => "Diária de Trabalho (Turno #{$shift->id})",
                'amount'      => $grossRate,
                'balance'     => $balance + $grossRate,
            ]);
            $balance += $grossRate;

            // Débito do consumo abatido
            if ($consumptionAmount > 0) {
                $employee->ledgers()->create([
                    'type'        => 'debit',
                    'description' => "Abatimento de Consumo Interno (Turno #{$shift->id})",
                    'amount'      => $consumptionAmount,
                    'balance'     => $balance - $consumptionAmount,
                ]);
                $balance -= $consumptionAmount;
            }

            // Débito do valor pago
            if ($paidAmount > 0) {
                $employee->ledgers()->create([
                    'type'        => 'debit',
                    'description' => "Liquidação Diária ({$paymentMethodLabel})",
                    'amount'                 => $paidAmount,
                    'balance'      => $balance - $paidAmount,
                ]);
            }

            // 5. Atualiza o status na escala do turno
            $shift->attendees()->updateExistingPivot($user->id, [
                'account_payable_id' => $payable->id,
                'daily_rate'         => $grossRate
            ]);
        });

        // 🚀 6. IMPRESSÃO AUTOMÁTICA DO COMPROVATIVO TÉRMICO
        // Suprime a impressão se a diária for apenas acumulada em saldo ou sem liquidação financeira imediata
        if ($paymentMethod === 'accumulate' || $paidAmount <= 0.00) {
            Log::info("ℹ️ [ShiftAttendance] Diária acumulada para [{$user->name}]. Impressão de recibo suprimida.");
            return;
        }

        try {
            $cashierPrinter = Printer::where('is_cashier', true)->where('is_active', true)->first()
                ?? Printer::where('is_active', true)->first();

            if ($cashierPrinter) {
                $receipt = new FreelancerReceipt(
                    freelancerName: $user->name,
                    netAmount: $netAmount,
                    grossAmount: $grossRate,
                    consumptionAmount: $consumptionAmount,
                    paymentMethod: $paymentMethodLabel,
                    operatorName: $operator->name ?? 'Caixa',
                    pixKey: $employee->pix_key
                );

                $this->printDispatcher->dispatch($cashierPrinter, $receipt);
                Log::info("🖨️ [ShiftAttendance] Recibo de freelancer impresso com sucesso para [{$user->name}].");
            }
        } catch (\Throwable $e) {
            Log::warning("⚠️ [ShiftAttendance] Falha ao imprimir recibo de freelancer: " . $e->getMessage());
        }
    }

    /**
     * 🚀 TRAVA DO CAIXA: Localiza freelancers presentes com diária pendente de pagamento
     */
    public function getPendingDailyFreelancers(CashShift $shift): Collection
    {
        return $shift->attendees()
            ->wherePivot('attendance_status', 'present')
            ->wherePivotNull('account_payable_id')
            ->whereHas('employee', function ($q) {
                $q->whereIn('employment_type', [
                    EmploymentType::FREELANCER->value,
                    EmploymentType::FREELANCE_FIXED->value,
                ])->orWhere('payment_cycle', PaymentCycle::DAILY->value);
            })
            ->get();
    }

    public function checkIsOnDuty(User $user): bool
    {
        $settings = \App\Models\CompanySetting::getCached();
        $isManager = $user->hasAnyRole(['admin', 'gerente', 'manager']);
        $isAttendanceRequired = $settings ? $settings->require_shift_attendance : false;

        if ($isManager || !$isAttendanceRequired) {
            return true;
        }

        return $user->isOnActiveShift();
    }

    public function startUserShift(User $user): void
    {
        $activeShift = CashShift::where('status', 'open')->latest()->first();

        if (!$activeShift) {
            throw new \Exception('Não é possível iniciar o turno. O Caixa da loja ainda está fechado.');
        }

        $dailyRate = (float) ($user->employee?->base_remuneration ?? 0);
        $primaryRole = $user->roles->first()?->name ?? 'waiter';

        $activeShift->attendees()->syncWithoutDetaching([
            $user->id => [
                'checked_in_at'     => now(),
                'status'            => 'active',
                'attendance_status' => 'present',
                'daily_rate'        => $dailyRate,
                'assigned_role'     => $primaryRole
            ]
        ]);
    }

    public static function getActiveShiftStaffByRoles(array $roles): \Illuminate\Support\Collection
    {
        $activeShift = CashShift::where('status', 'open')->latest()->first();

        if (!$activeShift) {
            return collect();
        }

        return $activeShift->attendees()
            ->wherePivot('status', 'active')
            ->wherePivot('attendance_status', 'present')
            ->where(function ($query) use ($roles) {
                $query->whereIn('shift_attendance.assigned_role', $roles)
                      ->orWhereHas('roles', function ($q) use ($roles) {
                          $q->whereIn('name', $roles);
                      });
            })
            ->get();
    }
}
