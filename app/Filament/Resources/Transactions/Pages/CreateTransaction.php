<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = TransactionResource::class;
}
