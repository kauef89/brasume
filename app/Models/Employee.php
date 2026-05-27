<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    // 🚀 Padrão Ouro: Libera todas as colunas para o Filament gerir
    protected $guarded = [];

    protected $casts = [
        "work_days" => "array",
        "base_remuneration" => "decimal:2",
        "transportation_voucher" => "decimal:2",
        "receives_night_premium" => "boolean",
        "receives_hazard_pay" => "boolean",
    ];

    public function vacations()
    {
        return $this->hasMany(EmployeeVacation::class);
    }

    // 🚀 A RELAÇÃO DA CONTA CORRENTE DO COLABORADOR
    public function ledgers()
    {
        return $this->hasMany(EmployeeLedger::class);
    }
}
