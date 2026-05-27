<?php

namespace App\Filament\Resources\AccountPayables\Pages;

use App\Filament\Resources\AccountPayables\AccountPayableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountPayable extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = AccountPayableResource::class;
}
