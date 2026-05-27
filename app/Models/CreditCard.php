<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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