<?php

namespace App\Filament\Resources\InventoryLosses\Pages;

use App\Filament\Resources\InventoryLosses\InventoryLossResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageInventoryLosses extends ManageRecords
{
    protected static string $resource = InventoryLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Registrar Perda Manual')
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
