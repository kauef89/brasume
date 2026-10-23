<?php

namespace App\Models;

use App\Enums\AcquirerMatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquirerTransaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'transaction_date'         => 'datetime',
        'expected_settlement_date' => 'date',
        'gross_amount'             => 'decimal:2',
        'fee_amount'               => 'decimal:2',
        'net_amount'               => 'decimal:2',
        'status'                   => AcquirerMatchStatus::class,
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class);
    }
}
