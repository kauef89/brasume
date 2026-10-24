<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \App\Models\BankAccount|null $bankAccount
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\Order|null $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountReceivable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountReceivable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountReceivable onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountReceivable query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountReceivable withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountReceivable withoutTrashed()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class AccountReceivable extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'gross_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
    public function customer() { return $this->belongsTo(Customer::class); }

    public function acquirerTransaction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AcquirerTransaction::class, 'account_receivable_id');
    }
}
