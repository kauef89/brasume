<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProductStorageLocation extends Model
{
    protected $table = 'product_storage_locations';

    protected $guarded = [];

    // Flag para evitar duplicação quando o InventoryService já gerou o Kardex
    public static bool $skipAutoKardex = false;

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
        $syncTotalStock = function (ProductStorageLocation $storageRule) {
            $product = $storageRule->product;
            if (!$product || !$product->track_inventory) {
                return;
            }

            $totalStock = round((float) $product->storageRules()->sum('current_stock'), 3);

            if (!static::$skipAutoKardex && $storageRule->wasChanged('current_stock')) {
                $oldLoc = (float) $storageRule->getOriginal('current_stock');
                $newLoc = (float) $storageRule->current_stock;
                $diff   = round($newLoc - $oldLoc, 4);

                if (abs($diff) >= 0.0001) {
                    $locName = $storageRule->location?->name ?? "Local #{$storageRule->storage_location_id}";
                    DB::table('inventory_movements')->insert([
                        'product_id'          => $product->id,
                        'storage_location_id' => $storageRule->storage_location_id,
                        'type'                => 'adjustment',
                        'quantity'            => $diff,
                        'balance_after'       => $totalStock,
                        'unit_cost'           => (float) ($product->base_cost ?? 0),
                        'description'         => "Ajuste via Painel WMS [{$locName}: {$oldLoc} → {$newLoc}]",
                        'user_id'             => auth()->id(),
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            }

            if (abs((float) $product->current_stock - $totalStock) >= 0.0001) {
                $product->current_stock = $totalStock;
                $product->saveQuietly();
            }
        };

        static::saved($syncTotalStock);
        static::deleted($syncTotalStock);
    }
}