<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\Supplier|null $supplier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CostHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CostHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CostHistory query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class CostHistory extends Model
{
    protected $guarded = [];

    public function product() { return $this->belongsTo(Product::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
}
