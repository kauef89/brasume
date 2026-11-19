<?php

namespace App\Filament\Resources\Payrolls\Pages;

use App\Enums\PayrollReferenceType;
use App\Filament\Resources\Payrolls\PayrollResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    public function getTitle(): string
    {
        return PayrollReferenceType::formatLongTitle($this->record->reference_period);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}