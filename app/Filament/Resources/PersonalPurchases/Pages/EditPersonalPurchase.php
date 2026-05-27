<?php

namespace App\Filament\Resources\PersonalPurchases\Pages;

use App\Filament\Resources\PersonalPurchases\PersonalPurchaseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPersonalPurchase extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = PersonalPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
