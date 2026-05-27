<?php

namespace App\Filament\Resources\Ingredients\Pages;

use App\Filament\Resources\Ingredients\IngredientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIngredient extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = IngredientResource::class;
}
