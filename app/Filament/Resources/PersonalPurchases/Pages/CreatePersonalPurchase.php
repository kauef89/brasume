<?php

namespace App\Filament\Resources\PersonalPurchases\Pages;

use App\Filament\Resources\PersonalPurchases\PersonalPurchaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonalPurchase extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = PersonalPurchaseResource::class;
}
