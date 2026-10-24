<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModifierGroup extends Pivot
{
    protected $table = 'modifier_group_product';
    public $incrementing = true;
    protected $guarded = [];

    protected $casts = [
        'excluded_options' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ProductModifier::class, 'modifier_group_id');
    }

    public function isOptionExcluded(int|string $optionId): bool
    {
        return in_array((int) $optionId, array_map('intval', $this->excluded_options ?? []), true);
    }
}