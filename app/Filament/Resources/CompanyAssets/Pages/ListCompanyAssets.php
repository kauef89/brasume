<?php

namespace App\Filament\Resources\CompanyAssets\Pages;

use App\Filament\Resources\CompanyAssets\CompanyAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanyAssets extends ListRecords
{
    protected static string $resource = CompanyAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
