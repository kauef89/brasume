<?php

namespace App\Filament\Resources\ChecklistLogs\Pages;

use App\Filament\Resources\ChecklistLogs\ChecklistLogResource;
use Filament\Resources\Pages\ListRecords;

class ListChecklistLogs extends ListRecords
{
    protected static string $resource = ChecklistLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}