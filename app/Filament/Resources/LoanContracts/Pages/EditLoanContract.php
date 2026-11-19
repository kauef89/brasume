<?php

namespace App\Filament\Resources\LoanContracts\Pages;

use App\Filament\Resources\LoanContracts\LoanContractResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLoanContract extends EditRecord
{
    protected static string $resource = LoanContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}