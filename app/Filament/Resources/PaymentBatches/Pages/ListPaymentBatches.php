<?php

namespace App\Filament\Resources\PaymentBatches\Pages;

use App\Filament\Resources\PaymentBatches\PaymentBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentBatches extends ListRecords
{
    protected static string $resource = PaymentBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}