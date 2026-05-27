<?php

namespace App\Filament\Resources\Neighborhoods\Pages;

use App\Filament\Resources\Neighborhoods\NeighborhoodResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditNeighborhood extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = NeighborhoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}