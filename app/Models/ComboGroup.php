<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
