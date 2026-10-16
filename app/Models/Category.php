<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Events\CatalogUpdated;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        "is_active" => "boolean",
        "sort_order" => "integer",
    ];

    protected $appends = ['image_url'];

        public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order', 'asc');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active_category', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->where('is_active', true);
        });
        static::saving(function (Category $category) {
            if (empty($category->slug) || $category->isDirty('name') || $category->isDirty('slug')) {
                $source = (!empty($category->slug) && $category->isDirty('slug')) ? $category->slug : $category->name;
                $category->slug = Str::slug($source ?? '');
            }
        });

        static::saved(function ($category) {
            $tenantId = $category->tenant_id ?? tenant('id') ?? 'central';

            Log::info("Atualizando catálogo. Avisando Tenant: {$tenantId}");

            try {
                if (method_exists(Cache::getStore(), 'tags')) {
                    Cache::tags(['catalog'])->flush();
                }
                Cache::forget('customer_catalog_data');
            } catch (\Exception $e) {
                Log::error("Aviso de Cache: " . $e->getMessage());
            }

            if (class_exists(CatalogUpdated::class)) {
                broadcast(new CatalogUpdated($tenantId));
            }
        });

        static::deleted(function ($category) {
            $tenantId = $category->tenant_id ?? tenant('id') ?? 'central';

            try {
                if (method_exists(Cache::getStore(), 'tags')) {
                    Cache::tags(['catalog'])->flush();
                }
                Cache::forget('customer_catalog_data');
            } catch (\Exception $e) {
                Log::error("Aviso de Cache (Delete): " . $e->getMessage());
            }

            if (class_exists(CatalogUpdated::class)) {
                broadcast(new CatalogUpdated($tenantId));
            }
        });

        static::forceDeleted(function (Category $category) {
            try {
                if ($category->image_path && Storage::disk('public')->exists($category->image_path)) {
                    Storage::disk('public')->delete($category->image_path);
                }
            } catch (\Exception $e) {
                Log::error("Erro ao apagar imagem da Categoria: " . $e->getMessage());
            }
        });
    }
}
