<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Product|null $ingredient
 * @property-read \App\Models\Product|null $parentProduct
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TechnicalSheet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TechnicalSheet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TechnicalSheet query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class TechnicalSheet extends Model
{
    // 🚀 O Modo Elegante: Aceita tudo que o Filament mandar.
    // O array vazio diz: "Nenhum campo está bloqueado para preenchimento em massa".
    protected $guarded = [];

    protected $casts = [
        'order_type' => 'array',
        'is_active' => 'boolean',
    ];


    // O Produto/Receita que está a ser montado
    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    // O Insumo ou Preparado que vai dentro da receita
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}
