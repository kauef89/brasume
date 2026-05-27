<?php

namespace App\Filament\Resources\PersonalPurchases\Pages;

use App\Filament\Resources\PersonalPurchases\PersonalPurchaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersonalPurchases extends ListRecords
{
    protected static string $resource = PersonalPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
