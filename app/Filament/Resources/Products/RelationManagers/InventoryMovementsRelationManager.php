<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryMovements';

    protected static ?string $title = 'Extrato Kardex (Histórico de Estoque)';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in'         => 'Entrada',
                        'out'        => 'Saída',
                        'loss'       => 'Perda / Quebra',
                        'adjustment' => 'Ajuste / Balanço',
                        default      => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in'         => 'success',
                        'out'        => 'info',
                        'loss'       => 'danger',
                        'adjustment' => 'warning',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd Movimentada')
                    ->numeric(decimalPlaces: 3)
                    ->color(fn ($state) => (float) $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => ((float) $state > 0 ? '+' : '') . number_format((float) $state, 3, ',', '.')),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo Resultante')
                    ->numeric(decimalPlaces: 3)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Custo Unitário')
                    ->money('BRL'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Motivo / Descrição')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('storageLocation.name')
                    ->label('Local')
                    ->placeholder('Geral / Padrão'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Operação')
                    ->options([
                        'in'         => 'Entrada',
                        'out'        => 'Saída',
                        'loss'       => 'Perda / Quebra',
                        'adjustment' => 'Ajuste / Balanço',
                    ]),
            ]);
    }
}
