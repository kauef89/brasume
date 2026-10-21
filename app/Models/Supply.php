<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;

class Supply extends Product
{
    protected $table = 'products';

    protected static function booted(): void
    {
        parent::booted();

        // 🚀 Escopo Global: este modelo apenas acede a embalagens e consumíveis
        static::addGlobalScope('supply_type', function (Builder $builder) {
            $builder->whereIn('type', [
                ProductType::PACKAGING->value,
                ProductType::CONSUMABLE->value,
            ]);
        });

        // 🚀 Ao criar por este modelo, assume por defeito o tipo CONSUMABLE
        static::creating(function (Supply $supply) {
            if (empty($supply->type)) {
                $supply->type = ProductType::CONSUMABLE->value;
            }
            $supply->show_on_menu = false;
            $supply->show_on_delivery = false;
        });
    }
}
