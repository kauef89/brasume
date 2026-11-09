<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOption extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price'             => 'float',
        'historical_weight' => 'float',
        'is_active'         => 'boolean',
        'show_on_pos'       => 'boolean',
        'show_on_customer_app' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductModifier::class, 'modifier_group_id');
    }

    public function linkedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'linked_product_id');
    }

    public function removesIngredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'removes_ingredient_id');
    }
}
