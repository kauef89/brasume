<?php

namespace App\Filament\Resources\CompliancePermits\Pages;

use App\Filament\Resources\CompliancePermits\CompliancePermitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompliancePermit extends EditRecord
{
    protected static string $resource = CompliancePermitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}