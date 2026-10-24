<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentItem extends Pivot
{
    protected $table = 'order_payment_items';
    public $incrementing = true;
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:4',
        'amount'   => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'order_payment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->item();
    }
}