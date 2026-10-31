<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAttendance extends Model
{
    protected $table = 'shift_attendances';
    protected $guarded = [];

    protected $casts = [
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
        'daily_rate'     => 'decimal:2',
    ];

    public function cashShift(): BelongsTo
    {
        return $this->shift();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'account_payable_id');
    }

    public function payable(): BelongsTo
    {
        return $this->accountPayable();
    }
}