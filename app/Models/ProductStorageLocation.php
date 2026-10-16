<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStorageLocation extends Model
{
    // 🚀 Nome real da tabela no banco
    protected $table = 'product_storage_location';

    protected $guarded = [];

    protected $casts = [
        'max_capacity' => 'integer',
        'restock_threshold' => 'integer',
        'current_stock' => 'decimal:3',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'source_location_id');
    }

    protected static function booted(): void
    {
        $syncTotalStock = function ($storageRule) {
            $product = $storageRule->product;

            if ($product && $product->track_inventory) {
                $totalStock = $product->storageRules()->sum('current_stock');
                $product->current_stock = $totalStock;
                $product->save();
            }
        };

        static::saved($syncTotalStock);
        static::deleted($syncTotalStock);
    }
}
