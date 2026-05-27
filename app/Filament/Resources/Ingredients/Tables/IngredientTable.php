<?php

namespace App\Filament\Resources\Ingredients\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Category;
use App\Models\Product;
use App\Filament\Resources\Ingredients\IngredientResource;
use Filament\Actions;

class IngredientTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Redireciona o clique da linha para a tela de Visualização (View)
            ->recordUrl(
                fn(Product $record): string => IngredientResource::getUrl(
                    "view",
                    ["record" => $record],
                ),
            )
            ->columns([
                Tables\Columns\TextColumn::make("name")
                    ->label("Insumo")
                    ->searchable()
                    ->sortable()
                    ->weight("bold"),

                Tables\Columns\TextColumn::make("category.name")
                    ->label("Categoria")
                    ->sortable(),

                Tables\Columns\TextColumn::make("current_stock")
                    ->label("Stock Atual")
                    ->numeric(3)
                    ->color(fn($state) => $state <= 0 ? "danger" : "primary")
                    ->weight("bold"),

                Tables\Columns\TextColumn::make("unit_of_measure")->label(
                    "U.M.",
                ),

                Tables\Columns\TextColumn::make("base_cost")
                    ->label("Custo Médio")
                    ->state(function ($record) {
                        $latestHistory = \App\Models\ProductCostHistory::where(
                            "product_id",
                            $record->id,
                        )
                            ->latest("id")
                            ->first();
                        $cost = $latestHistory
                            ? $latestHistory->unit_cost
                            : $record->base_cost;
                        return 'R$ ' .
                            number_format((float) $cost, 5, ",", ".");
                    })
                    ->color("success"),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("category_id")
                    ->label("Filtrar Categoria")
                    ->options(
                        fn() => Category::where("type", "internal")->pluck(
                            "name",
                            "id",
                        ),
                    ),
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
