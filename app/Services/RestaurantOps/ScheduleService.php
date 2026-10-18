<?php

namespace App\Services\RestaurantOps;

use App\Enums\EmployeeRole;
use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ScheduleSlot;
use App\Models\AccountPayable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function getWeeklySchedule(string $startDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        if (ScheduleSlot::count() === 0) {
            ScheduleSlot::create([
                'name' => 'Cozinha',
                'capacity' => 3,
                'sort_order' => 1,
                'allowed_roles' => ['kitchen']
            ]);
            ScheduleSlot::create([
                'name' => 'Salão / Atendimento',
                'capacity' => 2,
                'sort_order' => 2,
                'allowed_roles' => ['waiter']
            ]);
            ScheduleSlot::create([
                'name' => 'Caixa',
                'capacity' => 1,
                'sort_order' => 3,
                'allowed_roles' => ['cashier', 'manager']
            ]);
        }

        $rolesList = collect(EmployeeRole::cases())->map(fn ($r) => [
            'value' => $r->value,
            'label' => $r->label(),
        ])->values()->toArray();

        return [
            'employees' => Employee::with('user.roles')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function ($emp) {
                    $roles = $emp->user ? $emp->user->roles->pluck('name')->toArray() : [];
                    if (empty($roles) && !empty($emp->role)) {
                        $roles = [$emp->role];
                    }
                    $emp->setAttribute('active_roles', $roles);

                    $typeLabel = 'Geral';
                    if (!empty($emp->employment_type)) {
                        $enumInstance = EmploymentType::tryFrom($emp->employment_type);
                        if ($enumInstance) {
                            $typeLabel = $enumInstance->getLabel();
                        }
                    }
                    $emp->setAttribute('employment_type_label', $typeLabel);

                    return $emp;
                }),
            'slots' => ScheduleSlot::orderBy('sort_order')->get(),
            'availableRoles' => $rolesList,
            'schedules' => EmployeeSchedule::with(['employee.user.roles', 'slot'])
                ->whereBetween('date', [$start, $end])
                ->get(),
        ];
    }

    public function saveWeeklySchedule(string $startDate, array $scheduleData, ?array $slotsData = null): void
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        DB::transaction(function () use ($start, $end, $scheduleData, $slotsData) {
            $slotIdMap = [];

            if (!empty($slotsData)) {
                $processedSlotIds = [];

                foreach ($slotsData as $index => $slotInput) {
                    $rawId = $slotInput['id'] ?? null;
                    $name = trim($slotInput['name'] ?? '');
                    if (empty($name)) continue;

                    $capacity = max(1, (int)($slotInput['capacity'] ?? 1));
                    $sortOrder = (int)($slotInput['sort_order'] ?? ($index + 1));
                    $allowedRoles = !empty($slotInput['allowed_roles']) ? array_values($slotInput['allowed_roles']) : null;

                    $slot = null;
                    if ($rawId && is_numeric($rawId) && $rawId < 1000000000) {
                        $slot = ScheduleSlot::find($rawId);
                    }

                    if ($slot) {
                        $slot->update([
                            'name' => $name,
                            'capacity' => $capacity,
                            'sort_order' => $sortOrder,
                            'allowed_roles' => $allowedRoles,
                        ]);
                    } else {
                        $slot = ScheduleSlot::create([
                            'name' => $name,
                            'capacity' => $capacity,
                            'sort_order' => $sortOrder,
                            'allowed_roles' => $allowedRoles,
                        ]);
                    }

                    $processedSlotIds[] = $slot->id;
                    if ($rawId) {
                        $slotIdMap[$rawId] = $slot->id;
                    }
                }

                if (!empty($processedSlotIds)) {
                    ScheduleSlot::whereNotIn('id', $processedSlotIds)->delete();
                }
            }

            $validSlotIds = ScheduleSlot::pluck('id')->flip()->toArray();

            $existingSchedules = EmployeeSchedule::whereBetween('date', [$start, $end])
                ->get()
                ->keyBy(fn ($item) => $item->employee_id . '_' . $item->date->format('Y-m-d'));

            $processedKeys = [];

            foreach ($scheduleData as $entry) {
                $employeeId = $entry['employee_id'];
                $date = Carbon::parse($entry['date'])->format('Y-m-d');
                $key = $employeeId . '_' . $date;
                $processedKeys[] = $key;

                $isDayOff = (bool)($entry['is_day_off'] ?? false);
                $isExtra = $isDayOff ? false : (bool)($entry['is_extra_shift'] ?? false);

                $rawSlotId = $entry['schedule_slot_id'] ?? null;
                $slotId = $isDayOff ? null : ($slotIdMap[$rawSlotId] ?? $rawSlotId);

                if ($slotId !== null && !isset($validSlotIds[$slotId])) {
                    $slotId = null;
                }

                $role = $entry['assigned_role'] ?? ($isDayOff ? 'Folga' : 'Operação');

                $schedule = EmployeeSchedule::updateOrCreate(
                    ['employee_id' => $employeeId, 'date' => $date],
                    [
                        'schedule_slot_id' => $slotId,
                        'assigned_role' => $role,
                        'is_day_off' => $isDayOff,
                        'is_extra_shift' => $isExtra
                    ]
                );

                if ($isExtra && !$schedule->payable_id) {
                    $employee = Employee::find($employeeId);
                    $dailyRate = ($employee && $employee->base_remuneration > 0)
                        ? round($employee->base_remuneration / 30, 2)
                        : 60.00;

                    $payable = AccountPayable::create([
                        'description' => "Diária Extra (Escala 5x2) - " . Carbon::parse($date)->format('d/m/Y'),
                        'amount' => $dailyRate,
                        'due_date' => $date,
                        'status' => 'pending',
                        'is_personal' => false,
                        'employee_id' => $employeeId,
                        'accounting_category' => 'Folha de Pagamento',
                        'accounting_subcategory' => 'Diárias Extras'
                    ]);

                    $schedule->update(['payable_id' => $payable->id]);
                } elseif (!$isExtra && $schedule->payable_id) {
                    AccountPayable::where('id', $schedule->payable_id)->where('status', 'pending')->delete();
                    $schedule->update(['payable_id' => null]);
                }
            }

            $keysToRemove = array_diff($existingSchedules->keys()->toArray(), $processedKeys);
            foreach ($keysToRemove as $k) {
                $sch = $existingSchedules[$k];
                if ($sch->payable_id) {
                    AccountPayable::where('id', $sch->payable_id)->where('status', 'pending')->delete();
                }
                $sch->delete();
            }
        });
    }

    public function clonePreviousWeek(string $startDate): void
    {
        $currentStart = Carbon::parse($startDate)->startOfDay();
        $previousStart = $currentStart->copy()->subDays(7);
        $previousEnd = $previousStart->copy()->addDays(6)->endOfDay();

        $previousSchedules = EmployeeSchedule::whereBetween('date', [$previousStart, $previousEnd])->get();

        $newSchedules = [];
        foreach ($previousSchedules as $prev) {
            $diffInDays = $previousStart->diffInDays($prev->date);
            $newDate = $currentStart->copy()->addDays($diffInDays)->toDateString();

            $newSchedules[] = [
                'employee_id' => $prev->employee_id,
                'date' => $newDate,
                'schedule_slot_id' => $prev->schedule_slot_id,
                'assigned_role' => $prev->assigned_role,
                'is_day_off' => (bool)$prev->is_day_off,
                'is_extra_shift' => false
            ];
        }

        $this->saveWeeklySchedule($startDate, $newSchedules);
    }

    public function getTodayScheduledAttendees(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        return EmployeeSchedule::with(['employee.user.roles'])
            ->whereDate('date', $targetDate)
            ->where('is_day_off', false)
            ->get()
            ->map(function ($sch) {
                $user = $sch->employee->user;
                return [
                    'id' => $user?->id ?? $sch->employee_id,
                    'employee_id' => $sch->employee_id,
                    'user_id' => $user?->id,
                    'name' => $sch->employee->name,
                    'assigned_role' => $sch->assigned_role,
                    'slot_id' => $sch->schedule_slot_id,
                    'is_extra_shift' => (bool)$sch->is_extra_shift,
                ];
            })
            ->values()
            ->toArray();
    }
}
