<?php

namespace App\Filament\Resources\Supplies\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Category;
use App\Models\Product;
use App\Filament\Resources\Supplies\SupplyResource;
use Filament\Actions;

class SuppliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Redireciona o clique da linha para a tela de Visualização (View)
            ->recordUrl(fn (Product $record): string => SupplyResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Material')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->colors(['primary' => 'packaging', 'gray' => 'supply'])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'packaging' => 'Embalagem',
                        'supply' => 'Uso Interno',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock Atual')
                    ->numeric(2)
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->label('U.M.'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Filtrar Categoria')
                    ->options(fn () => Category::where('type', 'supply')->pluck('name', 'id'))
            ])
            ->actions([
                Actions\ViewAction::make()->iconButton(),
                Actions\EditAction::make()->iconButton(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}