<?php

namespace App\Filament\Resources\InventoryLosses\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Actions;
use App\Models\InventoryLoss;

class InventoryLossesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Columns\TextColumn::make('product.name')
                    ->label('Item Perdido')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (InventoryLoss $record) => $record->storageLocation ? "Local: {$record->storageLocation->name}" : null),

                Columns\TextColumn::make('loss_type')
                    ->label('Classificação')
                    ->badge()
                    ->sortable(),

                Columns\TextColumn::make('quantity')
                    ->label('Qtd.')
                    ->numeric(3)
                    ->formatStateUsing(fn ($state, $record) => number_format((float)$state, 2, ',', '.') . ' ' . strtoupper($record->product?->unit_of_measure ?? 'UN')),

                Columns\TextColumn::make('total_cost')
                    ->label('Prejuízo Total')
                    ->money('BRL')
                    ->sortable()
                    ->color('danger')
                    ->weight('black'),

                Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->searchable()
                    ->limit(35)
                    ->tooltip(fn ($record) => $record->notes),

                Columns\TextColumn::make('user.name')
                    ->label('Responsável')
                    ->placeholder('Sistema'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filters\SelectFilter::make('loss_type')
                    ->label('Tipo de Perda')
                    ->options(\App\Enums\InventoryLossType::class),

                Filters\SelectFilter::make('storage_location_id')
                    ->label('Local de Armazenamento')
                    ->relationship('storageLocation', 'name'),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ]);
    }
}
