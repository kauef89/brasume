<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LegacyOrderItem> $items
 * @property-read int|null $items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LegacyOrderPayment> $payments
 * @property-read int|null $payments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrder query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        "issue_date" => "datetime",
        "total_value" => "float",
        "discount" => "float",
    ];

    /**
     * @return BelongsTo<Customer,LegacyOrder>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // 🚀 A PEÇA QUE FALTAVA! O Filament estava a surtar sem isto:
    /**
     * @return BelongsTo<LegacyCustomer,LegacyOrder>
     */
    public function legacyCustomer(): BelongsTo
    {
        return $this->belongsTo(LegacyCustomer::class, 'legacy_customer_id');
    }

    /**
     * @return HasMany<LegacyOrderItem,LegacyOrder>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LegacyOrderItem::class);
    }

    // 🚀 Tipagem de retorno adicionada para garantir a estabilidade do Filament
    public function payments(): HasMany
    {
        // Um pedido tem muitos pagamentos legados
        return $this->hasMany(LegacyOrderPayment::class, "legacy_order_id");
    }
}
