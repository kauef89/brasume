<?php

namespace App\Models;

use App\Enums\CourierPaymentModel;
use App\Enums\EmployeeRole;
use App\Enums\EmploymentType;
use App\Enums\PaymentCycle;
use App\Models\AccountPayable;
use App\Models\Customer;
use App\Models\EmployeeLedger;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeVacation;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Payslip;
use App\Models\ShiftAttendance;
use App\Models\User;
use App\Observers\EmployeeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([EmployeeObserver::class])]
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $appends = [
        'meal_allowance',
        'active_roles',
        'is_courier',
    ];

    protected $casts = [
        'role'                   => EmployeeRole::class,
        'secondary_roles'        => 'array',
        'employment_type'        => EmploymentType::class,
        'payment_cycle'          => PaymentCycle::class,
        'work_days'              => 'array',
        'base_remuneration'      => 'decimal:2',
        'transportation_voucher' => 'decimal:2',
        'receives_night_premium' => 'boolean',
        'receives_hazard_pay'    => 'boolean',
        'food_allowance_amount'  => 'decimal:2',

        // Regras de Remuneração e Logística do Motoboy
        'courier_payment_model'  => CourierPaymentModel::class,
        'daily_fee'              => 'decimal:2',
        'default_delivery_fee'   => 'decimal:2',
        'fee_per_km'             => 'decimal:2',
        'minimum_guaranteed'     => 'decimal:2',
    ];

    protected $attributes = [
        'courier_payment_model' => 'fixed',
        'daily_fee'              => 0.00,
        'default_delivery_fee'   => 0.00,
        'fee_per_km'             => 0.00,
        'minimum_guaranteed'     => 0.00,
    ];

    /**
     * Retorna a lista unificada de papéis que o funcionário está apto a exercer na operação.
     */
    public function getAllRoles(): array
    {
        $primary = $this->role instanceof EmployeeRole
            ? $this->role->value
            : (is_string($this->role) ? $this->role : EmployeeRole::WAITER->value);

        $secondary = is_array($this->secondary_roles) ? $this->secondary_roles : [];

        return array_values(array_unique(array_filter(array_merge([$primary], $secondary))));
    }

    public function getActiveRolesAttribute(): array
    {
        return $this->getAllRoles();
    }

    public function isCourier(): bool
    {
        $roles = $this->getAllRoles();

        return in_array(EmployeeRole::COURIER->value, $roles, true)
            || in_array(EmployeeRole::COURIER->value, $roles, true)
            || in_array('motoboy', $roles, true);
    }

    public function getIsCourierAttribute(): bool
    {
        return $this->isCourier();
    }

    public function isClt(): bool
    {
        return $this->employment_type === EmploymentType::CLT;
    }

    public function isFreelancer(): bool
    {
        return in_array($this->employment_type, [
            EmploymentType::FREELANCER,
            EmploymentType::FREELANCE_FIXED,
        ], true);
    }

    /**
     * Teto de vale-alimentação.
     */
    public function getMealAllowanceAttribute(): float
    {
        return (float) ($this->attributes['meal_allowance'] ?? $this->food_allowance_amount ?? 0);
    }

    // =========================================================================
    // RELACIONAMENTOS
    // =========================================================================

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(EmployeeVacation::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(EmployeeLedger::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function kitchens(): BelongsToMany
    {
        return $this->belongsToMany(Kitchen::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class, 'employee_id');
    }

    public function accountPayables(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function payables(): HasMany
    {
        return $this->accountPayables();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Corridas / Entregas atribuídas a este motoboy
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    /**
     * Registros de presença em turnos de caixa
     */
    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            ShiftAttendance::class,
            User::class,
            'employee_id',
            'user_id',
            'id',
            'id'
        );
    }

    // =========================================================================
    // ACESSORES DE REMUNERAÇÃO
    // =========================================================================

    public function getMonthlySalaryAttribute(): float
    {
        return in_array($this->employment_type, [
            EmploymentType::CLT, EmploymentType::INTERN, EmploymentType::PJ, EmploymentType::PARTNER
        ]) ? (float) $this->base_remuneration : 0.0;
    }

    public function getDailyRateAttribute(): float
    {
        return in_array($this->employment_type, [
            EmploymentType::FREELANCER, EmploymentType::FREELANCE_FIXED
        ]) ? (float) $this->base_remuneration : 0.0;
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeCouriers($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('role', [EmployeeRole::COURIER->value, EmployeeRole::COURIER->value, 'motoboy'])
              ->orWhereJsonContains('secondary_roles', EmployeeRole::COURIER->value)
              ->orWhereJsonContains('secondary_roles', EmployeeRole::COURIER->value)
              ->orWhereJsonContains('secondary_roles', 'motoboy');
        });
    }
}
