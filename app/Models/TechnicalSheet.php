<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalSheet extends Model
{
    protected $guarded = [];

    protected $casts = [
        'order_type'  => 'array',
        'is_active'   => 'boolean',
        'is_dine_in'  => 'boolean',
        'is_takeaway' => 'boolean',
    ];

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}
