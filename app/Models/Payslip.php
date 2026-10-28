<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'employee_id',
        'base_salary',
        'gross_salary',
        'total_deductions',
        'net_salary',
        'inss_amount',
        'fgts_amount',
        'irrf_amount',
        'payment_method',
        'pix_key',
        'status',
        'bank_statement_id',
    ];

    protected $casts = [
        'base_salary'      => 'decimal:2',
        'gross_salary'     => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary'       => 'decimal:2',
        'inss_amount'      => 'decimal:2',
        'fgts_amount'      => 'decimal:2',
        'irrf_amount'      => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class);
    }

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class);
    }
}
