<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemModifier extends Model
{
    protected $guarded = [];
    protected $casts = ['price' => 'float', 'quantity' => 'integer'];

    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function modifierOption() { return $this->belongsTo(ModifierOption::class); }
}
