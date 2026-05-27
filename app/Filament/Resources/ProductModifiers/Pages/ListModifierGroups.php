<?php
namespace App\Filament\Resources\ProductModifiers\Pages;

use App\Filament\Resources\ProductModifiers\ProductModifierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModifierGroups extends ListRecords
{
    protected static string $resource = ProductModifierResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}