<?php

namespace App\Filament\Resources\FixedAccounts\Pages;

use App\Filament\Resources\FixedAccounts\FixedAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFixedAccount extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = FixedAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
