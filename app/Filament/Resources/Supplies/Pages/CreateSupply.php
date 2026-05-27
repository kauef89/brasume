<?php

namespace App\Filament\Resources\Supplies\Pages;

use App\Filament\Resources\Supplies\SupplyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupply extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = SupplyResource::class;
}
