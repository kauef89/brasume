<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'destination_account_id',
        'engine_type',
        'gateway_driver',
        'category',
        'fiscal_code',
        'show_on_pos',
        'show_on_delivery',
        'is_active',
    ];

    protected $casts = [
        'show_on_pos' => 'boolean',
        'show_on_delivery' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Scopes locais para reaproveitamento limpo
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPos(Builder $query): Builder
    {
        return $query->where('show_on_pos', true);
    }

    public function scopeForDelivery(Builder $query): Builder
    {
        return $query->where('show_on_delivery', true);
    }

    public function destinationAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\BankAccount::class, 'destination_account_id');
    }
}
