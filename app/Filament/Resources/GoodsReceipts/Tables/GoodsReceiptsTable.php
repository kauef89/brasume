<?php

namespace App\Filament\Resources\GoodsReceipts\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class GoodsReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('receipt_number')
                    ->label('Canhoto / Nº')
                    ->searchable()
                    ->placeholder('S/N'),

                TextColumn::make('received_at')
                    ->label('Data de Chegada')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Itens')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Status Fiscal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending_invoice' => 'Aguardando NF-e',
                        'reconciled' => 'Conciliado (Com NF-e)',
                        'cancelled' => 'Cancelado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending_invoice' => 'warning',
                        'reconciled' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('received_at', 'desc')
            ->actions([
                EditAction::make(),
            ]);
    }
}
