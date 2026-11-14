<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'raw_quantity'        => 'decimal:4',
        'conversion_factor'   => 'decimal:4',
        'quantity'            => 'decimal:4',
        'unit_cost_estimated' => 'decimal:5',
        'reconciled_qty'      => 'decimal:4',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
