<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\ModifierOption|null $currentOption
 * @property-read \App\Models\Product|null $currentProduct
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LegacyOrderItemModifier> $orderItemModifiers
 * @property-read int|null $order_item_modifiers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyModifier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyModifier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyModifier query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyModifier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_ignored' => 'boolean',
    ];

    // 🚀 Ligação corrigida para o nome exato do seu Model de opções!
    public function currentOption()
    {
        return $this->belongsTo(ModifierOption::class, "modifier_option_id");
    }

    // 🚀 Ensina o dicionário a olhar para a tabela de histórico de pedidos
        public function orderItemModifiers()
        {
            return $this->hasMany(LegacyOrderItemModifier::class, "legacy_modifier_id");
        }

        public function currentProduct()
            {
                return $this->belongsTo(Product::class, "current_product_id");
            }
}
