<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'installment_number' => 'integer',
        'due_date'           => 'date',
        'payment_date'       => 'date',
        'total_amount'       => 'decimal:2',
        'principal_portion'  => 'decimal:2',
        'interest_portion'   => 'decimal:2',
        'paid_amount'        => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LoanContract::class, 'loan_contract_id');
    }

    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'account_payable_id');
    }

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }
}