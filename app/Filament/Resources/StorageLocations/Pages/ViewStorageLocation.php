<?php

namespace App\Filament\Resources\StorageLocations\Pages;

use App\Filament\Resources\StorageLocations\StorageLocationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStorageLocation extends ViewRecord
{
    protected static string $resource = StorageLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
