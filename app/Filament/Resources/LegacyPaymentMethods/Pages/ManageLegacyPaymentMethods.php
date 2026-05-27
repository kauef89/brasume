<?php

namespace App\Filament\Resources\LegacyPaymentMethods\Pages;

use App\Filament\Resources\LegacyPaymentMethods\LegacyPaymentMethodResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLegacyPaymentMethods extends ManageRecords
{
    protected static string $resource = LegacyPaymentMethodResource::class;
}
