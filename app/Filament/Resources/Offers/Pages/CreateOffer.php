<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Filament\Resources\Offers\OfferResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOffer extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = OfferResource::class;
}