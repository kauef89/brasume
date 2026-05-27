<?php

namespace App\Filament\Resources\Kitchens\Pages;

use App\Filament\Resources\Kitchens\KitchenResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKitchen extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = KitchenResource::class;
}
