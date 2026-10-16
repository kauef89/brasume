<?php

namespace App\Filament\Resources\ShoppingListItems\Pages;

use App\Filament\Resources\ShoppingListItems\ShoppingListItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShoppingListItem extends ViewRecord
{
    protected static string $resource = ShoppingListItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
