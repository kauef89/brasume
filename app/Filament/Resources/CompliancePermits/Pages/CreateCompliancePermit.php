<?php

namespace App\Filament\Resources\CompliancePermits\Pages;

use App\Filament\Resources\CompliancePermits\CompliancePermitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompliancePermit extends CreateRecord
{
    protected static string $resource = CompliancePermitResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}