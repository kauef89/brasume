<?php

namespace App\Filament\Resources\FixedAccounts\Pages;

use App\Filament\Resources\FixedAccounts\FixedAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFixedAccounts extends ListRecords
{
    protected static string $resource = FixedAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
