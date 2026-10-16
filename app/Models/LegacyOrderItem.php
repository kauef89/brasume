<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\LegacyProduct|null $legacyProduct
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LegacyOrderItemModifier> $modifiers
 * @property-read int|null $modifiers_count
 * @property-read \App\Models\LegacyOrder|null $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderItem query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyOrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        "quantity" => "float",
        "unit_price" => "float",
        "total_price" => "float",
    ];
    /**
     * @return BelongsTo<LegacyOrder,LegacyOrderItem>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(LegacyOrder::class, "legacy_order_id");
    }
    /**
     * @return BelongsTo<LegacyProduct,LegacyOrderItem>
     */
    public function legacyProduct(): BelongsTo
    {
        return $this->belongsTo(LegacyProduct::class, "legacy_product_id");
    }

    public function modifiers()
        {
            return $this->hasMany(LegacyOrderItemModifier::class, "legacy_order_item_id");
        }
}
