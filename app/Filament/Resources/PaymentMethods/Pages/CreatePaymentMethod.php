<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = PaymentMethodResource::class;
}
