<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([ProductObserver::class])]
class Ingredient extends Product
{
    protected $table = 'products';

    /**
     * Força as chaves estrangeiras de relacionamentos hasMany (ex: costHistories, inventoryMovements)
     * a utilizarem product_id na tabela filha, e não ingredient_id.
     */
    public function getForeignKey(): string
    {
        return 'product_id';
    }

    public function getMorphClass(): string
    {
        return Product::class;
    }

    protected static function booted(): void
    {
        parent::booted();

        // 🚀 Escopo Global: este modelo acessa estritamente Insumos e Pré-preparos
        static::addGlobalScope('ingredient_type', function (Builder $builder) {
            $builder->whereIn('type', [
                ProductType::INGREDIENT->value,
                ProductType::MANUFACTURED->value,
            ]);
        });

        // 🚀 Valores padrão na criação
        static::creating(function (Ingredient $ingredient) {
            if (empty($ingredient->type)) {
                $ingredient->type = ProductType::INGREDIENT->value;
            }
            $ingredient->show_on_menu = false;
            $ingredient->show_on_delivery = false;
        });
    }
}
