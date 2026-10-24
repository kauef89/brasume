<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierItem extends Model
{
    protected $table = 'modifier_items';
    protected $guarded = [];

    protected $casts = [
        'extra_price' => 'decimal:2',
    ];

    public function modifierOption(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_id');
    }

    public function ingredientProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}