<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\ModifierOption|null $modifierOption
 * @property-read \App\Models\OrderItem|null $orderItem
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemModifier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemModifier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItemModifier query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class OrderItemModifier extends Model
{
    protected $guarded = [];
    protected $casts = ['price' => 'float', 'quantity' => 'integer'];

    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function modifierOption() { return $this->belongsTo(ModifierOption::class); }
}
