<?php

namespace App\Filament\Resources\StorageLocations\Pages;

use App\Filament\Resources\StorageLocations\StorageLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStorageLocations extends ListRecords
{
    protected static string $resource = StorageLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
