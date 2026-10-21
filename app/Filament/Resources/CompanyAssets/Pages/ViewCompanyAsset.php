<?php

namespace App\Filament\Resources\CompanyAssets\Pages;

use App\Filament\Resources\CompanyAssets\CompanyAssetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCompanyAsset extends ViewRecord
{
    protected static string $resource = CompanyAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
