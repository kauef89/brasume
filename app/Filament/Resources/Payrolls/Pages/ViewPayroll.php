<?php

namespace App\Filament\Resources\Payrolls\Pages;

use App\Filament\Resources\Payrolls\PayrollResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    public function getTitle(): string
    {
        $date = \Carbon\Carbon::parse($this->record->reference_period);
        return 'Folha de Pagamento - ' . ucfirst($date->translatedFormat('F / Y'));
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
