<?php

namespace App\Models;

use App\Enums\EmployeeRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleSlot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'capacity'      => 'integer',
        'sort_order'    => 'integer',
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

        // Substituição das strings manuais 'cashier', 'pos' e 'caixa' pelo Enum
        return in_array(EmployeeRole::CASHIER->value, $roles, true);
    }
}
