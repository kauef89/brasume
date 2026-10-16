<?php

namespace App\Filament\Resources\InventoryLosses\Pages;

use App\Filament\Resources\InventoryLosses\InventoryLossResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryLoss extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = InventoryLossResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }
}
