<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\ProductModifier|null $group
 * @property-read \App\Models\Product|null $linkedProduct
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModifierOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModifierOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModifierOption query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ModifierOption extends Model
{
    protected $guarded = [];
    protected $casts = ['price' => 'float'];

    public function group()
    {
        return $this->belongsTo(ProductModifier::class, 'modifier_group_id');
    }
    public function linkedProduct()
    {
        return $this->belongsTo(Product::class, 'linked_product_id'); // Abate estoque deste cara!
    }
}
