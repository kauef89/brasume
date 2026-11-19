<?php

namespace App\Filament\Resources\LoanContracts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';
    protected static ?string $title = 'Cronograma de Parcelas (Principal vs. Juros)';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('installment_number')
                    ->label('Parcela')
                    ->formatStateUsing(fn ($state, $record) => "{$state}/{$record->contract->installments_count}")
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('principal_portion')
                    ->label('Amortização (Principal)')
                    ->money('BRL')
                    ->color('gray'),

                TextColumn::make('interest_portion')
                    ->label('Juros / Encargos (DRE)')
                    ->money('BRL')
                    ->color('warning'),

                TextColumn::make('total_amount')
                    ->label('Valor Parcela')
                    ->money('BRL')
                    ->weight('bold'),

                TextColumn::make('payment_date')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid'    => 'success',
                        'overdue' => 'danger',
                        default   => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'paid'    => 'Quitada',
                        'overdue' => 'Vencida',
                        default   => 'Em Aberto',
                    }),
            ])
            ->defaultSort('installment_number', 'asc');
    }
}