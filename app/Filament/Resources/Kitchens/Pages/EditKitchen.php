<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Resources\Kitchens\KitchenResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditKitchen extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = KitchenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
