<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StorageLocation extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function productRules(): HasMany
    {
        return $this->hasMany(ProductStorageLocation::class, 'storage_location_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_storage_locations', 'storage_location_id', 'product_id')
                    ->withPivot('current_stock', 'max_capacity', 'restock_threshold', 'source_location_id')
                    ->withTimestamps();
    }
}
