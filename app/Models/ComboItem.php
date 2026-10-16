<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Category|null $category
 * @property-read \App\Models\ComboGroup|null $group
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComboItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComboItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComboItem query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ComboItem extends Model
{
    protected $guarded = [];
    protected $casts = ['additional_price' => 'float'];

    public function group() {
        return $this->belongsTo(ComboGroup::class, 'combo_group_id');
    }
    public function product() {
        return $this->belongsTo(Product::class);
    }
    public function category() {
        return $this->belongsTo(Category::class);
    }
}
