<?php

namespace App\Filament\Resources\LoanContracts\Pages;

use App\Filament\Resources\LoanContracts\LoanContractResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoanContract extends CreateRecord
{
    protected static string $resource = LoanContractResource::class;
}