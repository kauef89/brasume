<?php

namespace App\Models;

use App\Enums\CompliancePermitType;
use App\Enums\AccountingCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompliancePermit extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type'              => CompliancePermitType::class,
        'issued_at'         => 'date',
        'expires_at'        => 'date',
        'alert_days_before' => 'integer',
        'estimated_cost'    => 'decimal:2',
        'is_active'         => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->expires_at) return 'active';

        $today = Carbon::today();
        $alertDate = $this->expires_at->copy()->subDays($this->alert_days_before ?? 30);

        if ($this->expires_at->isPast()) {
            return 'expired';
        }

        if ($today->greaterThanOrEqualTo($alertDate)) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    public function getDaysRemainingAttribute(): int
    {
        if (!$this->expires_at) return 0;
        return (int) Carbon::today()->diffInDays($this->expires_at, false);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('expires_at', '>', Carbon::today()->addDays(30));
    }

    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('expires_at', '>=', Carbon::today())
            ->whereDate('expires_at', '<=', Carbon::today()->addDays(30));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('expires_at', '<', Carbon::today());
    }

    public function generatePayable(): ?AccountPayable
    {
        if ($this->account_payable_id && $this->accountPayable) {
            return $this->accountPayable;
        }

        $payable = AccountPayable::create([
            'description'            => "Renovação: {$this->name}",
            'amount'                 => (float) ($this->estimated_cost ?? 0),
            'due_date'               => $this->expires_at,
            'supplier_id'            => $this->supplier_id,
            'accounting_category'    => AccountingCategory::ADMINISTRATIVE->value,
            'accounting_subcategory' => 'taxes',
            'status'                 => 'pending',
            'is_personal'            => false,
        ]);

        $this->update(['account_payable_id' => $payable->id]);

        return $payable;
    }
}