<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Forma de Pagamento')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('destinationAccount.name')
                    ->label('Conta de Destino')
                    ->placeholder('Sem Vínculo (Perda/Cortesia)')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('engine_type')
                    ->label('Motor de Captura')
                    ->badge(),

                Tables\Columns\TextColumn::make('fiscal_code')
                    ->label('Cód. SEFAZ')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\ToggleColumn::make('show_on_pos')
                    ->label('PDV?'),

                Tables\Columns\ToggleColumn::make('show_on_delivery')
                    ->label('Delivery?'),

                Tables\Columns\ToggleColumn::make('auto_fiscal_emission')
                    ->label('Fiscal Auto?'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativo?'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ]);
    }
}
