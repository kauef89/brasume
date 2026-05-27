<?php

namespace App\Filament\Resources\LegacyNeighborhoods\Pages;

use App\Filament\Resources\LegacyNeighborhoods\LegacyNeighborhoodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLegacyNeighborhoods extends ManageRecords
{
    protected static string $resource = LegacyNeighborhoodResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->modalWidth("md")];
    }
}
