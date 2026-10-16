<?php

namespace App\Filament\Resources\StorageLocations\Pages;

use App\Filament\Resources\StorageLocations\StorageLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStorageLocation extends EditRecord
{
    protected static string $resource = StorageLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
