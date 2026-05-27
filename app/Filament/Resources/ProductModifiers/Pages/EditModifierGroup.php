<?php
namespace App\Filament\Resources\ProductModifiers\Pages;

use App\Filament\Resources\ProductModifiers\ProductModifierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModifierGroup extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = ProductModifierResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}