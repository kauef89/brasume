<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_period',
        'payment_date',
        'total_earnings',
        'total_deductions',
        'total_net',
        'total_inss',
        'total_fgts',
        'total_irrf',
        'status',
        'payment_batch_id',
        'notes',
    ];

    protected $casts = [
        'payment_date'     => 'date',
        'total_earnings'   => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net'        => 'decimal:2',
        'total_inss'       => 'decimal:2',
        'total_fgts'       => 'decimal:2',
        'total_irrf'       => 'decimal:2',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function paymentBatch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class);
    }
}
