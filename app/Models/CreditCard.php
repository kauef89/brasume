<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \App\Models\BankAccount|null $bankAccount
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditCard newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditCard newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditCard onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditCard query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditCard withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditCard withoutTrashed()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class CreditCard extends Model
{
    use SoftDeletes;
    protected $guarded = [];

    protected $casts = [
        'limit' => 'float',
        'is_active' => 'boolean',
    ];

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class);
    }
}
