<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Enums\CategoryType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordem')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Categoria Pai')
                    ->placeholder('Principal')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Qtd. Itens')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Macro-Área')
                    ->badge()
                    ->color(fn ($state): string => CategoryType::tryFrom((string) $state)?->getColor() ?? ($state === 'both' ? 'info' : 'primary'))
                    ->formatStateUsing(fn ($state): string => CategoryType::tryFrom((string) $state)?->getLabel() ?? ($state === 'both' ? 'Ambos' : ucfirst((string) $state)))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativa'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('type')
                    ->label('Filtrar por Macro-Área')
                    ->options(CategoryType::class),
            ])
            ->actions([
                EditAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
