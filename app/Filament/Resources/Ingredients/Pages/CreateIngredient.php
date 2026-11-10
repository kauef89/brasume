<?php

namespace App\Filament\Resources\Ingredients\Pages;

use App\Enums\ProductType;
use App\Filament\Resources\Ingredients\IngredientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIngredient extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = IngredientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['show_on_menu'] = false;
        $data['show_on_customer_app'] = false;
        $data['dine_in_price'] = null;
        $data['delivery_price'] = null;
        $data['promo_price'] = null;

        $isManufactured = ($data['type'] ?? null) === ProductType::MANUFACTURED->value;
        $data['requires_preparation'] = $isManufactured;

        if (empty($data['type'])) {
            $data['type'] = ProductType::INGREDIENT->value;
        }

        return $data;
    }
}
