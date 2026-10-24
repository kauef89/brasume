<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CashShift extends Model
{
    protected $guarded = [];

    protected $casts = [
        'opened_at'                => 'datetime',
        'closed_at'                => 'datetime',
        'opening_balance'          => 'decimal:2',
        'closing_balance_expected' => 'decimal:2',
        'closing_balance_actual'   => 'decimal:2',
        'accepting_orders'         => 'boolean',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function opener(): BelongsTo { return $this->openedBy(); }
    public function closer(): BelongsTo { return $this->closedBy(); }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getDifferenceAttribute(): float
    {
        if ($this->status === 'open' || is_null($this->closing_balance_expected) || is_null($this->closing_balance_actual)) {
            return 0.00;
        }
        return (float) ($this->closing_balance_actual - $this->closing_balance_expected);
    }

    /**
     * Colaboradores escalados e presentes no turno
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_attendance')
                    ->withPivot(
                        'checked_in_at',
                        'checked_out_at',
                        'status',
                        'attendance_status',
                        'daily_rate',
                        'assigned_role',
                        'account_payable_id'
                    )
                    ->withTimestamps();
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public static function current(): ?self
    {
        return static::open()->latest('id')->first();
    }
}
