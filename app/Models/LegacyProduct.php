<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\ModifierOption|null $currentModifier
 * @property-read \App\Models\Product|null $currentProduct
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyProduct query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyProduct extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_ignored' => 'boolean',
    ];

    // O link mágico para o presente
    public function currentProduct()
    {
        return $this->belongsTo(Product::class, 'current_product_id');
    }

    public function currentModifier()
    {
        // 🚀 O PADRÃO OURO: Aponta diretamente para o Eloquent Model correto!
        return $this->belongsTo(ModifierOption::class, 'current_modifier_id');
    }
}
