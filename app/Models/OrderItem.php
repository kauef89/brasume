<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function modifiers()
    {
        return $this->hasMany(OrderItemModifier::class, "order_item_id");
    }

    // 🚀 O Item Pai aponta para os Filhos
    public function subItems()
    {
        return $this->hasMany(OrderItem::class, "parent_item_id");
    }

    // 🚀 O Item Filho aponta para o Pai
    public function parentItem()
    {
        return $this->belongsTo(OrderItem::class, "parent_item_id");
    }
}
