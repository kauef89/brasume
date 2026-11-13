<?php

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Filament\Resources\BankAccounts\Actions\ImportCreditCardStatementAction;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBankAccount extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = BankAccountResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Detalhes da Conta';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-building-library';
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportCreditCardStatementAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}