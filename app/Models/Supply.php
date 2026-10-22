<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;

#[ObservedBy([ProductObserver::class])]
class Supply extends Product
{
    protected $table = 'products';

    /**
     * Força as chaves estrangeiras de relacionamentos hasMany a utilizarem product_id.
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

        // 🚀 Escopo Global: este modelo acessa estritamente Embalagens e Consumíveis Operacionais
        static::addGlobalScope('supply_type', function (Builder $builder) {
            $builder->whereIn('type', [
                ProductType::PACKAGING->value,
                ProductType::CONSUMABLE->value,
            ]);
        });

        // 🚀 Valores padrão na criação
        static::creating(function (Supply $supply) {
            if (empty($supply->type)) {
                $supply->type = ProductType::CONSUMABLE->value;
            }
            $supply->show_on_menu = false;
            $supply->show_on_delivery = false;
        });
    }
}
