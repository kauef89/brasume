<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\LegacyModifier|null $legacyModifier
 * @property-read \App\Models\LegacyOrderItem|null $orderItem
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderItemModifier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderItemModifier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderItemModifier query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyOrderItemModifier extends Model
{
    protected $guarded = [];

    protected $casts = [
        "quantity" => "float",
        "price" => "float",
    ];

    public function orderItem()
    {
        return $this->belongsTo(LegacyOrderItem::class, "legacy_order_item_id");
    }

    public function legacyModifier()
    {
        return $this->belongsTo(LegacyModifier::class, "legacy_modifier_id");
    }
}
