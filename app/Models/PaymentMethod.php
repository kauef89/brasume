<?php

namespace App\Models;

use App\Enums\PaymentEngineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'engine_type'          => PaymentEngineType::class,
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
        return $this->hasMany(Payment::class, 'payment_method_id');
    }
}
