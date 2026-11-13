<?php

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Filament\Resources\BankAccounts\Actions\ImportCreditCardStatementAction;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBankAccount extends ViewRecord
{
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
            Actions\EditAction::make(),
        ];
    }
}