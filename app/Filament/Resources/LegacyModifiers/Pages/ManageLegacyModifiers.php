<?php

namespace App\Filament\Resources\LegacyModifiers\Pages;

use App\Filament\Resources\LegacyModifiers\LegacyModifierResource;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\LegacyModifiers\Actions\BatchResolveModifiersAction;

class ManageLegacyModifiers extends ManageRecords
{
    protected static string $resource = LegacyModifierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BatchResolveModifiersAction::make(),
        ];
    }
}
