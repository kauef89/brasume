<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity'      => 'decimal:4',
        'balance_after' => 'decimal:4',
        'unit_cost'     => 'decimal:5',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Documento de origem polimórfico (Order, GoodsReceipt, InventoryLoss, etc.)
     */
    public function origin(): MorphTo
    {
        return $this->morphTo();
    }
}
