<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fired_at'          => 'datetime',
        'prep_time_minutes' => 'integer',
        'quantity'          => 'integer',
        'paid_qty'          => 'decimal:4',
        'unit_price'        => 'decimal:2',
        'total_price'       => 'decimal:2',
        'is_takeaway'       => 'boolean',
    ];

    protected $touches = ['order'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class, "order_item_id");
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function subItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, "parent_item_id");
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, "parent_item_id");
    }

    // 🚀 CORREÇÃO: Aponta para o Payment oficial da operação
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'order_payment_items', 'order_item_id', 'order_payment_id')
            ->withPivot('quantity', 'amount')
            ->withTimestamps();
    }
}
