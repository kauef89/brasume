<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    // 🚀 Padronização do projeto: elimina bloqueios de atribuição em massa
    protected $guarded = [];

    protected $casts = [
        'show_on_pos'          => 'boolean',
        'show_on_delivery'     => 'boolean',
        'auto_fiscal_emission' => 'boolean',
        'is_active'            => 'boolean',
    ];

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'destination_account_id');
    }

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class, 'payment_method_id');
    }
}
