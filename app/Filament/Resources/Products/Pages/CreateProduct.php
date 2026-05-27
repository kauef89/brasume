<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = ProductResource::class;
}
