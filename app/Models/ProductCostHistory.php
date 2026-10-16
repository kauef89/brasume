<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductCostHistory extends Model
{
    protected $guarded = [];

    public const SOURCE_NFE           = 'nfe';
    public const SOURCE_GOODS_RECEIPT = 'goods_receipt';
    public const SOURCE_MANUAL        = 'manual';

    protected $casts = [
        'unit_cost'         => 'decimal:5',
        'quantity'          => 'decimal:4',
        'conversion_factor' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function origin(): MorphTo
    {
        return $this->morphTo();
    }
}
