<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
