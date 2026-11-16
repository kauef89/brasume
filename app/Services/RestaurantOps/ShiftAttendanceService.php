<?php

namespace App\Services\RestaurantOps;

use App\Enums\AccountingCategory;
use App\Enums\EmploymentType;
use App\Enums\PaymentCycle;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Order;
use App\Models\Printer;
use App\Models\ShiftAttendance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Financial\CashFlowProjectionService;
use App\Services\Orders\CourierSettlementService;
use App\Services\Printing\Documents\FreelancerReceipt;
use App\Services\Printing\PrintDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftAttendanceService
{
    public function __construct(
        protected PrintDispatcher $printDispatcher,
        protected CashFlowProjectionService $cashFlowService
    ) {}

    /**
     * Formata qualquer data no padrão canônico humanizado: "DOM - 23/07"
     */
    public function formatShiftDate($datetime): string
    {
        $date = Carbon::parse($datetime);
        $days = [
            0 => 'DOM', 1 => 'SEG', 2 => 'TER', 3 => 'QUA',
            4 => 'QUI', 5 => 'SEX', 6 => 'SAB'
        ];
        $dayName = $days[$date->dayOfWeek] ?? 'DIA';
        return "{$dayName} - {$date->format('d/m')}";
    }

    /**
     * Retorna lista de colaboradores para o painel de ponto/abertura do caixa no PDV
     */
    public function getShiftUsersData(): array
    {
        $today = now()->toDateString();

        $todaySchedules = \App\Models\EmployeeSchedule::with('slot')
            ->whereDate('date', $today)
            ->get()
            ->keyBy('employee_id');

        $roleMap = [
            'admin'   => 'Administrador',
            'manager' => 'Gerente',
            'pos'     => 'Caixa',
            'cashier' => 'Caixa',
            'waiter'  => 'Garçom',
            'courier' => 'Motoboy',
            'driver'  => 'Motoboy',
            'kitchen' => 'Cozinha',
        ];

        return User::with(['employee', 'roles'])
            ->where('is_active', true)
            ->whereHas('employee', function ($query) {
                $query->where('is_active', true);
            })
            ->get()
            ->map(function ($u) use ($todaySchedules, $roleMap) {
                $emp = $u->employee;
                $dailyRate = 0;
                $isFreelancer = false;
                $isScheduled = false;
                $suggestedRole = null;

                if ($emp) {
                    $dailyRate = (float) ($emp->base_remuneration ?? 0);

                    $isFreelancer = in_array($emp->employment_type, [
                        EmploymentType::FREELANCE_FIXED,
                        EmploymentType::FREELANCER,
                        'freelancer',
                        'freelance_fixed'
                    ], true) || ($emp->employment_type instanceof EmploymentType && in_array($emp->employment_type->value, ['freelancer', 'freelance_fixed']));

                    if ($todaySchedules->has($emp->id)) {
                        $schedule = $todaySchedules->get($emp->id);
                        if (!$schedule->is_day_off) {
                            $isScheduled = true;
                            $suggestedRole = $schedule->assigned_role ?? $schedule->slot?->allowed_roles[0] ?? null;
                        }
                    } elseif (!empty($emp->work_days)) {
                        $hojeString = ['dom','seg','ter','qua','qui','sex','sab'][now()->dayOfWeek];
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
                    'assigned_role'   => $suggestedRole ?? ($availableRoles[0]['value'] ?? 'waiter'),
                    'default_rate'    => $dailyRate,
                    'daily_rate'      => $dailyRate,
                ];
            })
            ->sortByDesc('is_scheduled')
            ->values()
            ->toArray();
    }

    /**
     * Retorna presenças registradas no turno aberto
     */
    public function getCurrentAttendees(?CashShift $currentShift): array
    {
        if (!$currentShift) return [];

        $attendees = $currentShift->attendees()->get();
        $payableIds = $attendees->pluck('pivot.account_payable_id')->filter()->unique();
        $payables = $payableIds->isNotEmpty()
            ? AccountPayable::whereIn('id', $payableIds)->get()->keyBy('id')
            : collect();

        return $attendees->map(function($a) use ($payables) {
            $payableId = $a->pivot->account_payable_id;
            $payable = $payableId ? $payables->get($payableId) : null;
            $isRolled = false;

            if ($payable) {
                $isRolled = (float) $payable->amount <= 0.00
                    || str_contains($payable->observations ?? '', 'Acumulado no Saldo')
                    || str_contains($payable->description ?? '', 'Acúmulo de Saldo');
            }

            return [
                'user_id'            => $a->id,
                'status'             => $a->pivot->status,
                'attendance_status'  => $a->pivot->attendance_status,
                'daily_rate'         => (float) ($a->pivot->daily_rate ?? 0),
                'account_payable_id' => $payableId,
                'is_paid'            => !empty($payableId) && !$isRolled,
                'is_rolled'          => !empty($payableId) && $isRolled,
                'payout_status'      => !empty($payableId) ? ($isRolled ? 'rolled' : 'paid') : 'pending',
                'assigned_role'      => $a->pivot->assigned_role ?? null
            ];
        })->toArray();
    }

    /**
     * Sincroniza a equipe presente e encerra faltas/saídas
     */
    public function syncShiftAttendees(array $attendees, ?CashShift $shift = null): void
    {
        $shift = $shift ?? CashShift::where('status', 'open')->latest('id')->first();
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

            $status = $att['attendance_status'] ?? $att['status'] ?? 'present';
            $isAbsent = in_array($status, ['absent', 'day_off', 'vacation']);

            $syncData[$userId] = [
                'checked_in_at'     => $existing ? $existing->pivot->checked_in_at : now(),
                'status'            => $isAbsent ? 'completed' : 'active',
                'attendance_status' => $status,
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
                    'status'         => 'completed',
                    'checked_out_at' => now()
                ]);
            }
        }

        // 🚀 Se após a sincronização houver exatamente 1 motoboy presente no turno aberto,
        // assume automaticamente todos os pedidos de delivery em andamento sem entregador
        if (($shift->status instanceof \BackedEnum ? $shift->status->value : $shift->status) === 'open') {
            $courierService = app(CourierSettlementService::class);
            $activeCouriers = $courierService->getActiveCouriersOnShift($shift);
            if ($activeCouriers->count() === 1) {
                $courierService->autoAssignPendingOrdersToSoleCourier($activeCouriers->first(), $shift);
            }
        }
    }

    /**
     * Calcula e categoriza os consumos do turno por data e blocos
     */
    public function getFreelancerShiftConsumption(User $user, CashShift $shift): array
    {
        $employee = $user->employee ?? Employee::find($user->employee_id);
        $shiftStart = $shift->opened_at;
        $shiftDateLabel = $this->formatShiftDate($shiftStart);

        $ledgerDebits = 0.0;
        $items = [];

        if ($employee) {
            $debitEntries = $employee->ledgers()
                ->where('created_at', '>=', $shiftStart)
                ->where('type', 'debit')
                ->where('description', 'not like', '%Liquidação%')
                ->get();

            $ledgerDebits = (float) $debitEntries->sum('amount');

            foreach ($debitEntries as $entry) {
                $desc = $entry->description;
                $isExcedente = str_contains(mb_strtolower($desc), 'excedente');
                $category = $isExcedente ? 'excedente' : 'balcao';
                $categoryLabel = $isExcedente ? 'Excedente de Lanche' : 'Consumo Balcão';

                $cleanTitle = preg_replace('/\(Turno\s*#\d+\)/i', '', $desc);
                $cleanTitle = trim(preg_replace('/\s+/', ' ', $cleanTitle));

                $items[] = [
                    'id'             => 'ledger_' . $entry->id,
                    'ledger_id'      => $entry->id,
                    'type'           => 'consumption',
                    'category'       => $category,
                    'category_label' => $categoryLabel,
                    'title'          => $cleanTitle,
                    'amount'         => (float) $entry->amount,
                    'time'           => $entry->created_at->format('H:i'),
                    'date_label'     => $shiftDateLabel,
                    'selected'       => true,
                ];
            }
        }

        return [
            'total'         => round($ledgerDebits, 2),
            'ledger_debits' => round($ledgerDebits, 2),
            'orders_count'  => count($items),
            'items'         => $items,
            'date_label'    => $shiftDateLabel,
        ];
    }

    /**
     * Retorna a radiografia completa com os blocos categorizados para o modal de liquidação Vue
     */
    public function getFreelancerPayoutDetails(int $userId, ?CashShift $shift = null): array
    {
        $shift = $shift ?? CashShift::where('status', 'open')->latest('id')->first();
        if (!$shift) {
            throw new \Exception('Nenhum turno aberto encontrado.');
        }

        $user = User::with('employee')->findOrFail($userId);
        $employee = $user->employee;

        $shiftDateLabel = $this->formatShiftDate($shift->opened_at);

        $attendeePivot = $shift->attendees()->where('users.id', $userId)->first()?->pivot;
        $grossRate = (float) ($attendeePivot?->daily_rate ?: ($employee?->base_remuneration ?? 0));

        $consumptionData = $this->getFreelancerShiftConsumption($user, $shift);
        $consumptionAmount = (float) $consumptionData['total'];

        // O backend envia o BRUTO e o frontend (Vue) desconta na tela as checkboxes selecionadas.
        $netAmount = max(0, round($grossRate, 2));

        $latestLedger = $employee?->ledgers()->latest('id')->first();
        $previousBalance = $latestLedger ? (float) $latestLedger->balance : 0.00;
        $totalDue = round($previousBalance + $netAmount, 2);

        $serviceItem = [
            'id'             => 'service_shift_' . $shift->id,
            'type'           => 'service',
            'category'       => 'services',
            'category_label' => 'Serviços / Diária',
            'title'          => "Diária de Trabalho",
            'amount'         => $grossRate,
            'time'           => $shift->opened_at->format('H:i'),
            'date_label'     => $shiftDateLabel,
            'selected'       => true,
        ];

        $details = [
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'pix_key_type' => $employee?->pix_key_type,
                'pix_key'      => $employee?->pix_key,
            ],
            'shift_date_label'    => $shiftDateLabel,
            'service_item'        => $serviceItem,
            'previous_balance'    => $previousBalance,
            'ledger_history'      => $employee ? $employee->ledgers()->latest('id')->take(30)->get()->toArray() : [],
            'gross_rate'          => $grossRate,
            'consumption_amount'  => $consumptionAmount,
            'consumption_details' => $consumptionData,
            'net_amount'          => $netAmount,
            'total_due'           => $totalDue,
            'is_paid'             => (bool) ($attendeePivot?->account_payable_id),
        ];

        $shiftOperatorName = $shift->opened_by ? (User::find($shift->opened_by)?->name ?? 'Caixa') : 'Caixa';
        $details['shift_operator_name'] = $shiftOperatorName;
        $details['shift_opened_at'] = $shift->opened_at ? $shift->opened_at->format('d/m/Y H:i') : '';

        $assignedRole = (string) ($attendeePivot?->assigned_role ?? '');
        $isCourier = $employee && (
            $employee->is_courier
            || in_array($employee->job_title, ['driver', 'courier', 'motoboy'], true)
            || in_array($assignedRole, ['driver', 'courier', 'motoboy'], true)
            || $user->hasAnyRole(['driver', 'courier', 'motoboy'])
        );

        // Injeção do extrato de logística para motoboys
        if ($isCourier) {
            $courierService = app(CourierSettlementService::class);
            $statement = $courierService->getCourierStatement($employee, $shift->opened_at, now());

            $cashDeducted = (float) ($statement['cash_deducted'] ?? 0);

            $details['courier_details'] = [
                'is_courier'           => true,
                'courier_keeps_cash'   => (bool) ($statement['courier_keeps_cash'] ?? false),
                'payment_model'        => $statement['payment_model'],
                'payment_model_label'  => $statement['payment_model_label'],
                'default_delivery_fee' => $statement['default_delivery_fee'],
                'fee_per_km'           => $statement['fee_per_km'],
                'deliveries_count'     => $statement['deliveries_count'],
                'deliveries'           => $statement['deliveries_list'],
                'total_deliveries_fee' => $statement['total_deliveries_fee'],
                'daily_fee'            => $statement['daily_fee'],
                'minimum_guaranteed'   => $statement['minimum_guaranteed'],
                'minimum_applied'      => $statement['minimum_applied'],
                'minimum_complement'   => $statement['minimum_complement'],
                'final_payable'        => $statement['final_payable'],
                'cash_collected'       => $statement['cash_collected'],
                'cash_deducted'        => $cashDeducted,
                'net_cash_balance'     => $statement['net_cash_balance'],
            ];

            $details['gross_rate'] = $statement['final_payable'];
            $details['service_item']['amount'] = $statement['daily_fee'];
            $details['net_amount'] = max(0, $previousBalance + $statement['final_payable'] - $cashDeducted);
            $details['total_due'] = max(0, $previousBalance + $statement['final_payable'] - $cashDeducted);
        }

        return $details;
    }

    /**
     * Imprime a conferência prévia (pré-conta) da diária/corridas antes da liquidação financeira
     */
    public function printFreelancerConference(array $data, User $operator): void
    {
        $user = User::with('employee')->findOrFail($data['user_id']);
        $employee = $user->employee;

        $shift = CashShift::where('status', 'open')->latest('id')->first();
        if (!$shift) {
            throw new \Exception('Não existe nenhum turno aberto para emitir a conferência.');
        }

        $payoutDetails = $this->getFreelancerPayoutDetails($user->id, $shift);
        $shiftDateLabel = $data['shift_date_label'] ?? $payoutDetails['shift_date_label'];
        $shiftOperatorName = $payoutDetails['shift_operator_name'] ?? ($operator->name ?? 'Caixa');

        $grossRate = isset($data['gross_rate']) ? (float) $data['gross_rate'] : (float) $payoutDetails['gross_rate'];
        $consumptionAmount = isset($data['consumption_deducted']) ? (float) $data['consumption_deducted'] : (float) $payoutDetails['consumption_amount'];
        $netAmount = isset($data['amount']) ? (float) $data['amount'] : (float) ($payoutDetails['total_due'] ?? 0);

        // Se vieram itens do frontend, usá-los, senão monta a partir dos detalhes calculados
        $settledItems = !empty($data['settled_items']) ? $data['settled_items'] : [$payoutDetails['service_item']];
        
        if (empty($data['settled_items']) && !empty($payoutDetails['consumption_details']['items'])) {
            foreach ($payoutDetails['consumption_details']['items'] as $cons) {
                $settledItems[] = $cons;
            }
        }
        
        $rolledItems = !empty($data['rolled_items']) ? $data['rolled_items'] : [];
        $remainingBalance = isset($data['remaining_balance'])
            ? (float) $data['remaining_balance']
            : (float) ($payoutDetails['previous_balance'] ?? 0);

        $cashierPrinter = Printer::where('is_cashier', true)->where('is_active', true)->first()
            ?? Printer::where('is_active', true)->first();

        if (!$cashierPrinter) {
            throw new \Exception('Nenhuma impressora de caixa ativa configurada.');
        }

        $receipt = new FreelancerReceipt(
            freelancerName: $employee?->name ?? $user->name,
            netAmount: $netAmount,
            grossAmount: $grossRate,
            consumptionAmount: $consumptionAmount,
            paymentMethod: 'A Definir',
            operatorName: $operator->name ?? 'Caixa',
            pixKey: $employee?->pix_key,
            shiftDateLabel: $shiftDateLabel,
            settledItems: $settledItems,
            rolledItems: $rolledItems,
            remainingBalance: $remainingBalance,
            isConference: true,
            courierDetails: $payoutDetails['courier_details'] ?? null,
            shiftOperatorName: $shiftOperatorName
        );

        $this->printDispatcher->dispatch($cashierPrinter, $receipt);
        Log::info("🖨️ Conferência prévia de diária impressa para [{$user->name}].");
    }

    /**
     * Liquidação de freelancer/motoboy com sangria, débito em conta e emissão de recibo
     */
    public function payFreelancer(array $data, User $operator): void
    {
        $user = User::with('employee')->findOrFail($data['user_id']);
        $employee = $user->employee;

        if (!$employee) {
            throw new \Exception('O colaborador não tem um perfil de RH vinculado.');
        }

        $shift = CashShift::where('status', 'open')->latest('id')->first();
        if (!$shift) {
            throw new \Exception('Não existe nenhum turno aberto para registrar o pagamento.');
        }

        $payoutDetails = $this->getFreelancerPayoutDetails($user->id, $shift);
        $shiftDateLabel = $data['shift_date_label'] ?? $payoutDetails['shift_date_label'];

        $grossRate = (float) ($data['gross_rate'] ?? $payoutDetails['gross_rate']);
        $consumptionAmount = (float) ($data['consumption_deducted'] ?? $payoutDetails['consumption_amount']);
        $paidAmount = isset($data['amount']) ? (float) $data['amount'] : (float) ($payoutDetails['total_due'] ?? 0);

        $paymentMethod = $data['payment_method'];
        $bankAccountId = !empty($data['bank_account_id']) ? (int) $data['bank_account_id'] : null;

        $bankAccount = $bankAccountId ? BankAccount::find($bankAccountId) : null;
        $paymentMethodLabel = $paymentMethod === 'accumulate'
            ? 'Acumulado no Saldo (Sem repasse)'
            : ($paymentMethod === 'cash' ? 'Dinheiro (Gaveta)' : 'PIX (' . ($bankAccount?->name ?? 'Banco') . ')');

        $settledItems = $data['settled_items'] ?? [];
        $rolledItems = $data['rolled_items'] ?? [];
        $remainingBalanceAfter = 0.0;

        DB::transaction(function () use (
            $data, $user, $employee, $shift, $operator, $shiftDateLabel,
            $grossRate, $consumptionAmount, $paidAmount,
            $paymentMethod, $bankAccountId, $paymentMethodLabel,
            &$remainingBalanceAfter
        ) {
            $desc = "Diária {$shiftDateLabel} - {$user->name}";
            if ($consumptionAmount > 0) {
                $desc .= " (Bruto: R$ {$grossRate} - Consumo: R$ {$consumptionAmount})";
            }
            if ($paidAmount <= 0) {
                $desc = "Acúmulo de Saldo ({$shiftDateLabel}) - {$user->name}";
            }

            // 1. Contas a Pagar
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
                'observations'           => "Liquidado via {$paymentMethodLabel} - Turno {$shiftDateLabel}",
            ]);

            // 2. Sangria de gaveta
            if ($paymentMethod === 'cash' && $paidAmount > 0) {
                Transaction::create([
                    'cash_shift_id'   => $shift->id,
                    'bank_account_id' => $shift->bank_account_id,
                    'type'            => 'out',
                    'category'        => 'cash_out',
                    'amount'          => $paidAmount,
                    'description'     => "Sangria: Diária Freelancer - {$user->name}",
                    'operator_name'   => $operator->name ?? 'Sistema',
                    'transaction_date'=> now(),
                ]);
            }

            // 3. Saída Bancária
            if ($paymentMethod === 'bank' && $bankAccountId && $paidAmount > 0) {
                Transaction::create([
                    'cash_shift_id'   => null,
                    'bank_account_id' => $bankAccountId,
                    'type'            => 'out',
                    'category'        => 'bank_out',
                    'amount'          => $paidAmount,
                    'description'     => "PIX Diária Freelancer - {$user->name}",
                    'operator_name'   => $operator->name ?? 'Sistema',
                    'transaction_date'=> now(),
                ]);
            }

            // 4. Baixa as corridas do motoboy que estavam pendentes neste turno
            if ($employee && ($employee->is_courier || $user->hasAnyRole(['driver', 'courier', 'motoboy']))) {
                Order::where('courier_id', $employee->id)
                    ->whereNull('courier_settled_at')
                    ->whereBetween('created_at', [$shift->opened_at, now()])
                    ->update(['courier_settled_at' => now()]);
            }

            // 5. Conciliação na conta-corrente (EmployeeLedger)
            $latestLedger = $employee->ledgers()->latest('id')->first();
            $balance = $latestLedger ? (float) $latestLedger->balance : 0.00;

            $employee->ledgers()->create([
                'type'        => 'credit',
                'description' => "Diária/Produção ({$shiftDateLabel})",
                'amount'      => $grossRate,
                'balance'     => $balance + $grossRate,
            ]);
            $balance += $grossRate;

            if ($paidAmount > 0) {
                $employee->ledgers()->create([
                    'type'        => 'debit',
                    'description' => "Liquidação ({$paymentMethodLabel})",
                    'amount'      => $paidAmount,
                    'balance'     => $balance - $paidAmount,
                ]);
                $balance -= $paidAmount;
            }

            $remainingBalanceAfter = round($balance, 2);

            // 6. Atualização da presença
            $shift->attendees()->updateExistingPivot($user->id, [
                'account_payable_id' => $payable->id,
                'daily_rate'         => $grossRate
            ]);
        });

        // 7. Impressão Térmica do Recibo
        if ($paymentMethod === 'accumulate' && $paidAmount <= 0.00 && empty($settledItems)) {
            Log::info("ℹ️ Diária acumulada para [{$user->name}]. Impressão suprimida.");
            return;
        }

        try {
            $cashierPrinter = Printer::where('is_cashier', true)->where('is_active', true)->first()
                ?? Printer::where('is_active', true)->first();

            if ($cashierPrinter) {
                $receipt = new FreelancerReceipt(
                    freelancerName: $employee->name,
                    netAmount: $paidAmount,
                    grossAmount: $grossRate,
                    consumptionAmount: $consumptionAmount,
                    paymentMethod: $paymentMethodLabel,
                    operatorName: $operator->name ?? 'Caixa',
                    pixKey: $employee->pix_key,
                    shiftDateLabel: $shiftDateLabel,
                    settledItems: $settledItems,
                    rolledItems: $rolledItems,
                    remainingBalance: $remainingBalanceAfter,
                    isConference: false,
                    courierDetails: $payoutDetails['courier_details'] ?? null,
                    shiftOperatorName: $payoutDetails['shift_operator_name'] ?? ($operator->name ?? 'Caixa')
                );

                $this->printDispatcher->dispatch($cashierPrinter, $receipt);
                Log::info("🖨️ Recibo estruturado impresso para [{$user->name}].");
            }
        } catch (\Throwable $e) {
            Log::warning("⚠️ Falha ao imprimir recibo estruturado: " . $e->getMessage());
        }
    }

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

    public function checkIsOnDuty($user): bool
    {
        if (!$user) return false;

        $userModel = is_numeric($user) ? User::find($user) : $user;
        if (!$userModel) return false;

        $settings = CompanySetting::getCached();
        $isManager = $userModel->hasAnyRole(['admin', 'gerente', 'manager', 'owner']);
        $isAttendanceRequired = $settings ? $settings->require_shift_attendance : false;

        if ($isManager || !$isAttendanceRequired) {
            return true;
        }

        if (method_exists($userModel, 'isOnActiveShift')) {
            return $userModel->isOnActiveShift();
        }

        $openShift = CashShift::where('status', 'open')->latest('id')->first();
        if (!$openShift) return false;

        return ShiftAttendance::where('cash_shift_id', $openShift->id)
            ->where('user_id', $userModel->id)
            ->where('attendance_status', 'present')
            ->whereNull('checked_out_at')
            ->exists();
    }

    public function startUserShift(User $user): void
    {
        $activeShift = CashShift::where('status', 'open')->latest('id')->first();

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

        // 🚀 Se o colaborador que iniciou o turno for o único motoboy ativo, carimba os deliveries pendentes
        $courierService = app(CourierSettlementService::class);
        $activeCouriers = $courierService->getActiveCouriersOnShift($activeShift);
        if ($activeCouriers->count() === 1) {
            $courierService->autoAssignPendingOrdersToSoleCourier($activeCouriers->first(), $activeShift);
        }
    }

    public static function getActiveShiftStaffByRoles(array $roles): Collection
    {
        $activeShift = CashShift::where('status', 'open')->latest('id')->first();

        if (!$activeShift) {
            return collect();
        }

        return $activeShift->attendees()
            ->wherePivot('status', 'active')
            ->wherePivot('attendance_status', 'present')
            ->where(function ($query) use ($roles) {
                $query->whereIn('shift_attendances.assigned_role', $roles)
                      ->orWhereHas('roles', function ($q) use ($roles) {
                          $q->whereIn('name', $roles);
                      });
            })
            ->get();
    }
}
