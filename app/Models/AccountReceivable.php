<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivable extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'due_date'       => 'date',
        'payment_date'   => 'date',
        'gross_amount'   => 'decimal:2',
        'fee_amount'     => 'decimal:2',
        'net_amount'     => 'decimal:2',
        'transaction_id' => 'integer',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function transaction() { return $this->belongsTo(Transaction::class); }

    public function acquirerTransaction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AcquirerTransaction::class, 'account_receivable_id');
    }
}
