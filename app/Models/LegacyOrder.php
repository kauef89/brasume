<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    /**
     * @return HasMany<LegacyOrderItem,LegacyOrder>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LegacyOrderItem::class);
    }
    public function payments()
    {
        // Um pedido tem muitos pagamentos legados
        return $this->hasMany(LegacyOrderPayment::class, "legacy_order_id");
    }
}
