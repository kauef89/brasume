<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\PaymentCycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        "employment_type"        => EmploymentType::class,
        "payment_cycle"          => PaymentCycle::class,
        "work_days"              => "array",
        "base_remuneration"      => "decimal:2",
        "transportation_voucher" => "decimal:2",
        "receives_night_premium" => "boolean",
        "receives_hazard_pay"    => "boolean",
        'food_allowance_amount'  => "decimal:2",
    ];

    /**
     * Utilizador de sistema autenticável vinculado a este perfil de colaborador
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
}
