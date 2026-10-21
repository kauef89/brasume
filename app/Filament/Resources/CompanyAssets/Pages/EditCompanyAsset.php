<?php

namespace App\Filament\Resources\CompanyAssets\Pages;

use App\Filament\Resources\CompanyAssets\CompanyAssetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompanyAsset extends EditRecord
{
    protected static string $resource = CompanyAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
