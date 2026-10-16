<?php

namespace App\Filament\Resources\FiscalDocuments\Pages;

use App\Filament\Resources\FiscalDocuments\FiscalDocumentResource;
use App\Filament\Resources\FiscalDocuments\Actions\ExportFiscalDataAction;
use Filament\Resources\Pages\ListRecords;

class ListFiscalDocuments extends ListRecords
{
    protected static string $resource = FiscalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportFiscalDataAction::make(),
        ];
    }
}
