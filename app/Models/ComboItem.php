<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
