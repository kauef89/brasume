<?php

namespace App\Filament\Resources\PosAudits\Pages;

use App\Filament\Resources\PosAudits\PosAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListPosAudits extends ListRecords
{
    protected static string $resource = PosAuditResource::class;

    protected function getHeaderActions(): array
    {
        // Sem botão de criar!
        return [];
    }
}
