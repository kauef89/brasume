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
        'is_day_off'       => 'boolean',
        'is_extra_shift'   => 'boolean',
        'schedule_slot_id' => 'integer',
        'employee_id'      => 'integer',
        'payable_id'       => 'integer',
    ];

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
