<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleSlot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'capacity' => 'integer',
        'sort_order' => 'integer',
        'allowed_roles' => 'array',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class, 'schedule_slot_id');
    }

    /**
     * Identifica se este slot de escala tem atribuição de Operador de Caixa
     */
    public function isCashierSlot(): bool
    {
        $roles = $this->allowed_roles ?? [];

        return in_array('cashier', $roles, true)
            || in_array('pos', $roles, true)
            || str_contains(mb_strtolower((string) $this->name), 'caixa');
    }
}
