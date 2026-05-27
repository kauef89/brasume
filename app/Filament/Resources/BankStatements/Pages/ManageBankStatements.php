<?php

namespace App\Filament\Resources\BankStatements\Pages;

use App\Filament\Resources\BankStatements\BankStatementResource;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\BankStatements\Actions\AutoConciliateAction;
use App\Filament\Resources\BankStatements\Actions\ImportOfxAction;

class ManageBankStatements extends ManageRecords
{
    protected static string $resource = BankStatementResource::class;

    // Propriedades do Livewire necessárias para o modal de conciliação funcionar
    public array $skippedIds = [];
    public ?int $currentStmtId = null;

    protected function getHeaderActions(): array
    {
        return [
            // 🚀 Toda a mágica foi modularizada!
            AutoConciliateAction::make(),
            ImportOfxAction::make(),
        ];
    }
}
