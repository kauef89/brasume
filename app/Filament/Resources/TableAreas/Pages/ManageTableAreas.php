<?php

namespace App\Filament\Resources\TableAreas\Pages;

use App\Filament\Resources\TableAreas\TableAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTableAreas extends ManageRecords
{
    protected static string $resource = TableAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
