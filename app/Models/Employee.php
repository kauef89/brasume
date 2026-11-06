<?php

namespace App\Models;

use App\Enums\EmployeeRole;
use App\Enums\EmploymentType;
use App\Enums\PaymentCycle;
use App\Observers\EmployeeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy([EmployeeObserver::class])]
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $appends = [
        'meal_allowance',
        'active_roles',
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

    /**
     * Teto de vale-alimentação.
     */
    public function getMealAllowanceAttribute(): float
    {
        return (float) ($this->attributes['meal_allowance'] ?? $this->food_allowance_amount ?? 0);
    }

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

    /**
     * Holerites / Recibos de Pagamento (Exigido pelo PayslipsRelationManager no Filament)
     */
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
}
