<?php

namespace App\Models;

use App\Enums\ConciliationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_type'    => ConciliationType::class,
        'auto_reconcile' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'target_account_id');
    }
}