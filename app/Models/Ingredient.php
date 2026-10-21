<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;

class Ingredient extends Product
{
    protected $table = 'products';

    protected static function booted(): void
    {
        parent::booted();

        // 🚀 Escopo Global: este modelo apenas acede a insumos e preparados internos
        static::addGlobalScope('ingredient_type', function (Builder $builder) {
            $builder->whereIn('type', [
                ProductType::INGREDIENT->value,
                ProductType::MANUFACTURED->value,
            ]);
        });

        // 🚀 Ao criar por este modelo, assume por defeito o tipo INGREDIENT
        static::creating(function (Ingredient $ingredient) {
            if (empty($ingredient->type)) {
                $ingredient->type = ProductType::INGREDIENT->value;
            }
            $ingredient->show_on_menu = false;
            $ingredient->show_on_delivery = false;
        });
    }
}
