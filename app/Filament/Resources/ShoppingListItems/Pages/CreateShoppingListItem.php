<?php

namespace App\Filament\Resources\ShoppingListItems\Pages;

use App\Filament\Resources\ShoppingListItems\ShoppingListItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShoppingListItem extends CreateRecord
{
    protected static string $resource = ShoppingListItemResource::class;
}
