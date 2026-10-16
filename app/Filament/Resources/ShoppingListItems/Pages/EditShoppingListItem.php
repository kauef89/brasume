<?php

namespace App\Filament\Resources\ShoppingListItems\Pages;

use App\Filament\Resources\ShoppingListItems\ShoppingListItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditShoppingListItem extends EditRecord
{
    protected static string $resource = ShoppingListItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
