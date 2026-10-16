<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use App\Events\CatalogUpdated;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ModifierOption> $options
 * @property-read int|null $options_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductModifier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductModifier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductModifier onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductModifier query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductModifier withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductModifier withoutTrashed()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ProductModifier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'modifier_groups';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_quantity' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'max_free' => 'integer',
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
            $tenantId = $modifierGroup->tenant_id ?? tenant('id') ?? 'central';

            try {
                \Illuminate\Support\Facades\Cache::tags(['catalog'])->flush();
            } catch (\Exception $e) { }

            broadcast(new CatalogUpdated($tenantId));
        });

        static::deleted(function ($modifierGroup) {
            $tenantId = $modifierGroup->tenant_id ?? tenant('id') ?? 'central';

            try {
                \Illuminate\Support\Facades\Cache::tags(['catalog'])->flush();
            } catch (\Exception $e) { }

            broadcast(new CatalogUpdated($tenantId));
        });
    }
}
