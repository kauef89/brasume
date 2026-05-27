<?php

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Filament\Resources\BankAccounts\BankAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankAccount extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = BankAccountResource::class;
}
