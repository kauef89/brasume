<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;
    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'transaction_date' => 'datetime',
    ];

    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
    public function paymentMethod() { return $this->belongsTo(PaymentMethod::class); }
    public function order() { return $this->belongsTo(Order::class); }
}