<?php
namespace App\Filament\Resources\ProductModifiers\Pages;

use App\Filament\Resources\ProductModifiers\ProductModifierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModifierGroup extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = ProductModifierResource::class;
}