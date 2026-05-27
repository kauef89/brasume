<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
