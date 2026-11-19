<?php

namespace App\Filament\Resources\LoanContracts\Pages;

use App\Filament\Resources\LoanContracts\LoanContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoanContracts extends ListRecords
{
    protected static string $resource = LoanContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo Contrato de Empréstimo'),
        ];
    }
}