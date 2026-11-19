<?php

namespace App\Models;

use App\Enums\EmployeeRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSchedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date'             => 'date',
        'shift_type'       => \App\Enums\ScheduleShiftType::class,
        'is_day_off'       => 'boolean',
        'is_extra_shift'   => 'boolean',
        'schedule_slot_id' => 'integer',
        'employee_id'      => 'integer',
        'payable_id'       => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeSchedule $schedule) {
            if ($schedule->isDirty('shift_type') && $schedule->shift_type !== null) {
                $type = $schedule->shift_type instanceof \App\Enums\ScheduleShiftType
                    ? $schedule->shift_type
                    : (\App\Enums\ScheduleShiftType::tryFrom((string) $schedule->shift_type) ?? \App\Enums\ScheduleShiftType::REGULAR);

                $schedule->is_day_off = $type === \App\Enums\ScheduleShiftType::DAY_OFF;
                $schedule->is_extra_shift = $type === \App\Enums\ScheduleShiftType::EXTRA_SHIFT;
            } elseif ($schedule->isDirty('is_day_off') || $schedule->isDirty('is_extra_shift') || empty($schedule->shift_type)) {
                $isDayOff = (bool) $schedule->is_day_off;
                $isExtra = $isDayOff ? false : (bool) $schedule->is_extra_shift;

                $schedule->is_day_off = $isDayOff;
                $schedule->is_extra_shift = $isExtra;
                $schedule->shift_type = \App\Enums\ScheduleShiftType::fromFlags($isDayOff, $isExtra);
            }
        });
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'payable_id');
    }

    /**
     * Determina se este registro de escala confere função de Caixa ao colaborador
     */
    public function isCashierSchedule(): bool
    {
        if ($this->is_day_off) {
            return false;
        }

        // Substituição de ['caixa', 'cashier', 'pos'] pelo valor tipado do Enum
        if ($this->assigned_role === EmployeeRole::CASHIER->value) {
            return true;
        }

        return $this->slot?->isCashierSlot() ?? false;
    }
}
