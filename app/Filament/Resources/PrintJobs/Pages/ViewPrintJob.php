<?php

namespace App\Filament\Resources\PrintJobs\Pages;

use App\Filament\Resources\PrintJobs\PrintJobResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPrintJob extends ViewRecord
{
    protected static string $resource = PrintJobResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
