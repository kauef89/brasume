<?php

namespace App\Filament\Resources\FixedAccounts\Pages;

use App\Filament\Resources\FixedAccounts\FixedAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFixedAccount extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = FixedAccountResource::class;
}
