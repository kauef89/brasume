<?php

namespace App\Filament\Resources\StorageLocations\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class StorageLocationsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Rota')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Local')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Categoria')
                    ->badge()
                    ->colors([
                        'primary' => 'display',
                        'warning' => 'intermediate',
                        'gray' => 'storage',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'display' => 'Exposição',
                        'intermediate' => 'Intermediário',
                        'storage' => 'Retaguarda',
                        default => $state,
                    }),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Filtrar por Tipo')
                    ->options([
                        'display' => 'Exposição',
                        'intermediate' => 'Intermediário',
                        'storage' => 'Retaguarda',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
