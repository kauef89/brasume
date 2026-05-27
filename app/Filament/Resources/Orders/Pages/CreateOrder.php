<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = OrderResource::class;
}