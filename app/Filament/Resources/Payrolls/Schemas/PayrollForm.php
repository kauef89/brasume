<?php

namespace App\Filament\Resources\Payrolls\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

class PayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('reference_period')
                ->label('Mês de Referência')
                ->displayFormat('m/Y')
                ->required(),

            DatePicker::make('payment_date')
                ->label('Data de Pagamento')
                ->required(),

            Select::make('status')
                ->label('Estado')
                ->options([
                    'draft'    => 'Rascunho / Aberta',
                    'approved' => 'Aprovada para Pagamento',
                    'paid'     => 'Paga / Finalizada',
                ])
                ->required(),
        ]);
    }
}
