<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\AcquirerTransaction;

class Order extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'pix_expires_at' => 'datetime',
        'address_snapshot'       => 'array',
        'address'                => 'array',
        'payment_intent_methods' => 'array',
        'change_target'          => 'decimal:2',
        'subtotal'               => 'decimal:2',
        'delivery_fee'           => 'decimal:2',
        'service_fee'            => 'decimal:2',
        'discount'               => 'decimal:2',
        'total'                  => 'decimal:2',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    protected $appends = [
        'has_fiscal_document',
        'fiscal_status',
        'total_paid',
        'balance_due',
    ];

    protected $attributes = [
        'payment_intent_status' => 'pending_choice',
    ];

    public const SOURCE_CUSTOMER_APP       = 'customer_app';
    public const SOURCE_CUSTOMER_APP_TABLE = 'customer_app_table';
    public const SOURCE_POS_APP            = 'pos_app';
    public const SOURCE_CASHIER            = 'cashier';
    public const SOURCE_TEAM_APP           = 'team_app';
    public const SOURCE_KIOSK              = 'kiosk';

    public const VALID_SOURCES = [
        self::SOURCE_CUSTOMER_APP,
        self::SOURCE_CUSTOMER_APP_TABLE,
        self::SOURCE_POS_APP,
        self::SOURCE_CASHIER,
        self::SOURCE_TEAM_APP,
        self::SOURCE_KIOSK,
    ];

    // =========================================================================
    // RELACIONAMENTOS DE CLIENTE E LOGÍSTICA
    // =========================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function addressModel(): BelongsTo
    {
        return $this->deliveryAddress();
    }

    public function getAddressAttribute($value): ?array
    {
        if (!empty($value)) {
            return is_string($value) ? (json_decode($value, true) ?: null) : $value;
        }

        if (!empty($this->attributes['address_snapshot'])) {
            $snap = $this->attributes['address_snapshot'];
            return is_string($snap) ? (json_decode($snap, true) ?: null) : $snap;
        }

        if ($this->relationLoaded('deliveryAddress') && $this->deliveryAddress) {
            return [
                'id'           => $this->deliveryAddress->id,
                'street'       => $this->deliveryAddress->street?->name ?? ($this->deliveryAddress->street ?? ''),
                'number'       => $this->deliveryAddress->no_number ? 'S/N' : ($this->deliveryAddress->number ?? ''),
                'neighborhood' => $this->deliveryAddress->neighborhood?->name ?? ($this->deliveryAddress->neighborhood ?? ''),
                'complement'   => $this->deliveryAddress->complement,
                'full_address' => $this->deliveryAddress->full_address,
                'delivery_fee' => (float) $this->deliveryAddress->delivery_fee,
            ];
        }

        return null;
    }

    public function setAddressAttribute($value): void
    {
        $json = is_array($value) ? json_encode($value) : $value;
        $this->attributes['address_snapshot'] = $json;
        $this->attributes['address'] = $json;
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Utilizador (Garçom / Operador) que lançou o pedido no sistema
     */
    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Alias semântico para atendente de salão
     */
    public function waiter(): BelongsTo
    {
        return $this->attendant();
    }

    /**
     * Alias de retrocompatibilidade com queries existentes (Order::with('employee'))
     */
    public function employee(): BelongsTo
    {
        return $this->attendant();
    }

    /**
     * Operador de caixa responsável pelo registo da comanda
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Accessor para aceder ao perfil formal de RH do atendente (se existir)
     */
    public function getStaffProfileAttribute(): ?Employee
    {
        if ($this->relationLoaded('attendant') && $this->attendant) {
            return $this->attendant->relationLoaded('employee')
                ? $this->attendant->employee
                : $this->attendant->employee()->first();
        }

        return null;
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function table(): BelongsTo
    {
        return $this->diningTable();
    }

    // =========================================================================
    // RELACIONAMENTOS DE ITENS E FISCAL
    // =========================================================================

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function fiscalDocument(): HasOne
    {
        return $this->hasOne(FiscalDocument::class)->latestOfMany();
    }

    public function rating(): HasOne
    {
        return $this->hasOne(OrderRating::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    // =========================================================================
    // RELACIONAMENTOS FINANCEIROS
    // =========================================================================

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function accountReceivables(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    // =========================================================================
    // ACESSORES E MOTOR DE CÁLCULO DE PAGAMENTOS
    // =========================================================================

    public function getTotalPaidAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->where('is_paid', true)->sum('amount');
        }

        return (float) $this->payments()->where('is_paid', true)->sum('amount');
    }

    public function getBalanceDueAttribute(): float
    {
        $total = (float) ($this->total ?? 0);
        $balance = $total - $this->total_paid;
        return $balance > 0 ? round($balance, 2) : 0.00;
    }

    public function recalculatePaymentStatus(): void
    {
        $totalPaid = $this->total_paid;
        $totalOrder = (float) $this->total;

        if ($totalPaid <= 0) {
            $status = 'pending';
        } elseif ($totalPaid < ($totalOrder - 0.01)) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }

        $this->updateQuietly(['payment_status' => $status]);
    }

    public function getHasFiscalDocumentAttribute(): bool
    {
        return $this->fiscalDocument?->status === 'authorized';
    }

    public function getFiscalStatusAttribute(): ?string
    {
        return $this->fiscalDocument?->status;
    }

    public function fiscalDocuments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\FiscalDocument::class);
    }

    public function cashShift(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function acquirerTransactions(): HasMany
    {
        return $this->hasMany(AcquirerTransaction::class);
    }
}
