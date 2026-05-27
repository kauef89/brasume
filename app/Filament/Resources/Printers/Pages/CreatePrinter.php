<?php

namespace App\Filament\Resources\Printers\Pages;

use App\Filament\Resources\Printers\PrinterResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrinter extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = PrinterResource::class;
}
