<?php

namespace App\Filament\Resources\Streets\Pages;

use App\Filament\Resources\Streets\StreetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStreet extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = StreetResource::class;
}