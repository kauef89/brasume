<?php

namespace App\Models;

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
        'role',
        'meal_allowance',
    ];

    protected $casts = [
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
     * Retorna o cargo oficial (job_title) ou infere pelo tipo de contratação.
     */
    public function getRoleAttribute(): string
    {
        if (!empty(trim($this->job_title ?? ''))) {
            return trim($this->job_title);
        }

        $type = $this->employment_type instanceof EmploymentType
            ? $this->employment_type->value
            : (string) ($this->employment_type ?? '');

        return match ($type) {
            'partner'         => 'Sócio',
            'freelance_fixed' => 'Diarista',
            'freelancer'      => 'Freelancer',
            'pj'              => 'Prestador PJ',
            'clt'             => 'Colaborador',
            default           => 'Equipe',
        };
    }

    /**
     * Ponte de compatibilidade para leitura do teto de vale-alimentação.
     */
    public function getMealAllowanceAttribute(): float
    {
        return (float) ($this->attributes['meal_allowance'] ?? $this->food_allowance_amount ?? 0);
    }

    /**
     * Cliente correspondente no PDV para compras e consumo interno.
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Usuário autenticável vinculado ao colaborador.
     */
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
