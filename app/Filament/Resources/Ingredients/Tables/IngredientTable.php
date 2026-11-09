<?php

namespace App\Filament\Resources\Ingredients\Tables;

use App\Filament\Resources\Ingredients\IngredientResource;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IngredientTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(
                fn (Product $record): string => IngredientResource::getUrl(
                    'view',
                    ['record' => $record],
                ),
            )
            ->defaultSort('cost_variation', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Insumo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Estoque Atual')
                    ->numeric(3)
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->label('U.M.'),

                Tables\Columns\TextColumn::make('base_cost')
                    ->label('Custo Médio')
                    ->state(function ($record) {
                        $cost = $record->latest_unit_cost ?? $record->base_cost;
                        return 'R$ ' . number_format((float) $cost, 4, ',', '.');
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('cost_variation')
                    ->label('Variação de Custo')
                    ->state(function ($record) {
                        $current = (float) ($record->latest_unit_cost ?? 0);
                        $previous = (float) ($record->previous_unit_cost ?? 0);

                        if ($current <= 0 || $previous <= 0) {
                            return $current > 0 ? '1ª Compra' : 'Sem compras';
                        }

                        $diffPercent = (($current - $previous) / $previous) * 100;
                        $prefix = $diffPercent > 0 ? '+' : '';

                        return $prefix . number_format($diffPercent, 1, ',', '.') . '%';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $current = (float) ($record->latest_unit_cost ?? 0);
                        $previous = (float) ($record->previous_unit_cost ?? 0);

                        if ($current <= 0 || $previous <= 0) return 'gray';

                        $diffPercent = (($current - $previous) / $previous) * 100;

                        if ($diffPercent > 80) return 'danger';
                        if ($diffPercent > 20) return 'warning';
                        if ($diffPercent < -5) return 'success';
                        return 'gray';
                    })
                    ->icon(function ($record) {
                        $current = (float) ($record->latest_unit_cost ?? 0);
                        $previous = (float) ($record->previous_unit_cost ?? 0);

                        if ($current <= 0 || $previous <= 0) return null;

                        $diffPercent = (($current - $previous) / $previous) * 100;

                        if ($diffPercent > 80) return 'heroicon-m-exclamation-triangle';
                        if ($diffPercent > 0) return 'heroicon-m-arrow-trending-up';
                        if ($diffPercent < -5) return 'heroicon-m-arrow-trending-down';
                        return null;
                    })
                    ->description(function ($record) {
                        $current = (float) ($record->latest_unit_cost ?? 0);
                        $previous = (float) ($record->previous_unit_cost ?? 0);

                        if ($current > 0 && $previous > 0) {
                            $prevFmt = 'R$ ' . number_format($previous, 2, ',', '.');
                            $currFmt = 'R$ ' . number_format($current, 2, ',', '.');
                            return "De {$prevFmt} para {$currFmt}";
                        }
                        return null;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $expr = "
                            (
                                ((SELECT unit_cost FROM product_cost_histories WHERE product_id = products.id ORDER BY id DESC LIMIT 1)
                                 -
                                 (SELECT unit_cost FROM product_cost_histories WHERE product_id = products.id ORDER BY id DESC LIMIT 1 OFFSET 1))
                                /
                                NULLIF((SELECT unit_cost FROM product_cost_histories WHERE product_id = products.id ORDER BY id DESC LIMIT 1 OFFSET 1), 0)
                            )
                        ";

                        return $query
                            ->orderByRaw("({$expr} IS NULL) ASC")
                            ->orderByRaw("{$expr} {$direction}");
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Filtrar Categoria')
                    ->options(
                        fn () => Category::where('type', 'internal')->pluck(
                            'name',
                            'id',
                        ),
                    ),

                Tables\Filters\Filter::make('anomalous_cost_spike')
                    ->label('🚨 Salto Suspeito (> 50% de aumento)')
                    ->query(fn (Builder $query): Builder => $query->whereRaw("
                        (SELECT unit_cost FROM product_cost_histories WHERE product_id = products.id ORDER BY id DESC LIMIT 1 OFFSET 1) > 0
                        AND (
                            (
                                (SELECT unit_cost FROM product_cost_histories WHERE product_id = products.id ORDER BY id DESC LIMIT 1)
                                -
                                (SELECT unit_cost FROM product_cost_histories WHERE product_id = products.id ORDER BY id DESC LIMIT 1 OFFSET 1)
                            )
                            /
                            (SELECT unit_cost FROM product_cost_histories WHERE product_id = products.id ORDER BY id DESC LIMIT 1 OFFSET 1)
                        ) >= 0.50
                    ")),
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
