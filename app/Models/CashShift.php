<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashShift extends Model
{
    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_balance' => 'decimal:2',
        'closing_balance_expected' => 'decimal:2',
        'closing_balance_actual' => 'decimal:2',
    ];

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class);
    }

    public function opener() {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer() {
        return $this->belongsTo(User::class, 'closed_by');
    }
    
    // Calcula a "Quebra de Caixa" (Diferença)
    public function getDifferenceAttribute() {
        if ($this->status === 'open' || is_null($this->closing_balance_expected) || is_null($this->closing_balance_actual)) {
            return 0;
        }
        return $this->closing_balance_actual - $this->closing_balance_expected;
    }
}