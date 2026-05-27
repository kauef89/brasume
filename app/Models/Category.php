<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str; // <-- 1. ADICIONE ESTA IMPORTAÇÃO AQUI NO TOPO!

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        "is_active" => "boolean",
        "sort_order" => "integer",
    ];

    // ==========================================
    // 2. A MÁGICA: O MODEL GERA O PRÓPRIO SLUG!
    // ==========================================
    protected static function booted(): void
    {
        // Antes de CRIAR no banco, ele pega o nome e transforma em slug
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        // Antes de ATUALIZAR, se o nome mudou, ele refaz o slug
        static::updating(function ($category) {
            if ($category->isDirty("name")) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // ==========================================
    // RELAÇÕES
    // ==========================================
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, "parent_id");
    }
    /**
     * @return HasMany<Category,Category>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, "parent_id");
    }
    /**
     * @return HasMany<Product,Category>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
