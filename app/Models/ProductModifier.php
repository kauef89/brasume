<?php

namespace App\Models;

use App\Enums\ModifierType;
use App\Events\CatalogUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class ProductModifier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'modifier_groups';

    protected $guarded = [];

    protected $casts = [
        'type'           => ModifierType::class,
        'is_active'      => 'boolean',
        'allow_quantity' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'max_free'       => 'integer',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class, 'modifier_group_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'modifier_group_product', 'modifier_group_id', 'product_id')
            ->withPivot('excluded_options')
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saved(function ($modifierGroup) {
            $tenantId =$modifierGroup->tenant_id ?? tenant('id') ?? 'central';

            try {
                Cache::tags(['catalog'])->flush();
            } catch (\Exception $e) { }

            broadcast(new CatalogUpdated($tenantId));
        });

        static::deleted(function ($modifierGroup) {
            $tenantId =$modifierGroup->tenant_id ?? tenant('id') ?? 'central';

            try {
                Cache::tags(['catalog'])->flush();
            } catch (\Exception $e) { }

            broadcast(new CatalogUpdated($tenantId));
        });
    }
}
