<?php

namespace App\Filament\Resources\AccountPayables\Pages;

use App\Filament\Resources\AccountPayables\AccountPayableResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\AccountPayables\Actions\ReverseConciliationAction;

class EditAccountPayable extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = AccountPayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A MÁGICA DRY: Reaproveitamento total na tela de edição!
            ReverseConciliationAction::configure(Action::make('conciliar_reverso')),

            DeleteAction::make(),
        ];
    }
}
