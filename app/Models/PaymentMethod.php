<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;
    protected $guarded = [];

    protected $casts = [
        'show_on_pos' => 'boolean',
        'show_on_delivery' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Para onde vai o dinheiro quando o cliente paga com esta forma?
    public function destinationAccount() {
        return $this->belongsTo(BankAccount::class, 'destination_account_id');
    }
}