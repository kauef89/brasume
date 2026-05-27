<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;
    
    // Caminho livre para gravação (o Filament faz a segurança no formulário)
    protected $guarded = [];

    protected $casts = [
        'initial_balance' => 'float',
        'is_active' => 'boolean',
        
        // ==========================================
        // NOVOS CASTS: REGRAS DE RECEBIMENTO
        // ==========================================
        'debit_fee' => 'decimal:2',
        'credit_fee' => 'decimal:2',
        'anticipation_fee' => 'decimal:2',
        'debit_term_days' => 'integer',
        'credit_term_days' => 'integer',
        'anticipation_days' => 'array', // Transforma o array do Filament num JSON no banco
    ];

    public function creditCards() {
        return $this->hasMany(CreditCard::class);
    }

    public function paymentMethods() {
        return $this->hasMany(PaymentMethod::class, 'destination_account_id');
    }
}