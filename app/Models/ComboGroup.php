<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Product|null $comboProduct
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ComboItem> $items
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComboGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComboGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComboGroup query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ComboGroup extends Model
{
    protected $guarded = [];

    public function comboProduct() {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function items() {
        return $this->hasMany(ComboItem::class);
    }
}
