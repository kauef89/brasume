<?php

namespace App\Filament\Resources\Neighborhoods\Pages;

use App\Filament\Resources\Neighborhoods\NeighborhoodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNeighborhood extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = NeighborhoodResource::class;
}