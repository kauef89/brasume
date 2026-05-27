<?php

namespace App\Filament\Resources\LegacyNeighborhoods\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Tables\Filters\TernaryFilter;

class LegacyNeighborhoodsTable
{
    public static function getTableColumns(): array
    {
        return [
            TextColumn::make('legacy_name')
                ->label('Bairro no CSV')
                ->searchable()
                ->sortable(),

            // 🚀 Atualizado para neighborhood.name
            TextColumn::make('neighborhood.name')
                ->label('Bairro Mapeado (V3)')
                ->searchable()
                ->sortable()
                ->badge()
                ->color(fn ($record) => $record->neighborhood_id ? 'success' : 'danger')
                ->default('Não Mapeado'),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::getTableColumns())
            ->filters([
                TernaryFilter::make('is_mapped')
                    ->label('Status do Mapeamento')
                    ->trueLabel('Mapeados')
                    ->falseLabel('Não Mapeados')
                    ->queries(
                        // 🚀 Filtros atualizados para neighborhood_id
                        true: fn ($query) => $query->whereNotNull('neighborhood_id'),
                        false: fn ($query) => $query->whereNull('neighborhood_id'),
                    ),
            ])
            ->actions([
                EditAction::make()->modalWidth('md'),
            ]);
    }
}
