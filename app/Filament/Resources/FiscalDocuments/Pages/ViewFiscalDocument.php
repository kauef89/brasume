<?php

namespace App\Filament\Resources\FiscalDocuments\Pages;

use App\Filament\Resources\FiscalDocuments\FiscalDocumentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFiscalDocument extends ViewRecord
{
    protected static string $resource = FiscalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
