<?php

namespace App\Filament\Resources\ProductModifiers\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\Ingredients\Schemas\IngredientForm;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = "options";
    protected static ?string $title = "Opções";

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            FormComponents\TextInput::make("name")
                ->label("Nome da Opção")
                ->required()
                ->maxLength(255),

            FormComponents\TextInput::make("price")
                ->label('Preço Adicional (R$)')
                ->numeric()
                ->default(0)
                ->required(),

            // 🚀 NOSSA NOVA INJEÇÃO AQUI: A QUANTIDADE MÁXIMA
            FormComponents\TextInput::make("max_quantity")
                ->label("Qtd. Máxima")
                ->numeric()
                ->default(1)
                ->helperText("Limite por pedido.")
                ->required(),

            FormComponents\Select::make("linked_product_id")
                ->label("Abater Estoque de Qual Insumo? (Opcional)")
                ->relationship(
                    name: "linkedProduct",
                    titleAttribute: "name",
                    modifyQueryUsing: fn(Builder $query) => $query->whereIn(
                        "type",
                        ["ingredient", "manufactured"],
                    ),
                )
                ->searchable()
                ->preload()
                ->live()
                ->createOptionForm(IngredientForm::getFormSchema())
                ->createOptionAction(function (Actions\Action $action) {
                    return $action
                        ->modalHeading("Cadastrar Novo Insumo")
                        ->modalSubmitActionLabel("Salvar e Selecionar")
                        ->modalWidth("4xl");
                }),

            FormComponents\TextInput::make("linked_qty")
                ->label("Quantidade da Porção")
                ->numeric()
                ->step("0.0001")
                ->hidden(fn(Get $get) => blank($get("linked_product_id")))
                ->suffix(function (Get $get) {
                    $productId = $get("linked_product_id");
                    if (blank($productId)) {
                        return null;
                    }

                    $product = \App\Models\Product::find($productId);
                    return $product
                        ? strtoupper($product->unit_of_measure)
                        : null;
                }),

            FormComponents\TextInput::make("sort_order")
                ->label("Ordem")
                ->numeric()
                ->default(0),

            FormComponents\Toggle::make("show_on_pos")
                ->label("Exibir no PDV / Salão (TeamApp)")
                ->helperText("Visível para os garçons e atendentes.")
                ->default(true)
                ->inline(false),

            FormComponents\Toggle::make("show_on_delivery")
                ->label("Exibir no Delivery (App Cliente)")
                ->helperText("Visível no cardápio online do cliente.")
                ->default(true)
                ->inline(false),

            FormComponents\Toggle::make("is_active")
                ->label("Ativo?")
                ->default(true)
                ->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute("name")
            ->columns([
                Tables\Columns\TextColumn::make("sort_order")
                    ->label("Ordem")
                    ->sortable(),

                Tables\Columns\TextColumn::make("name")
                    ->label("Nome da Opção")
                    ->searchable(),

                Tables\Columns\TextColumn::make("price")
                    ->label("Preço Venda")
                    ->money("BRL")
                    ->sortable()
                    ->color("primary"),

                Tables\Columns\TextColumn::make("max_quantity")
                    ->label("Máximo")
                    ->badge()
                    ->color("info")
                    ->formatStateUsing(fn ($state) => $state . "x"),

                Tables\Columns\TextColumn::make("linkedProduct.name")
                    ->label("Insumo")
                    ->searchable()
                    ->placeholder("-"),

                Tables\Columns\TextColumn::make("linked_qty")
                    ->label("Porção")
                    ->formatStateUsing(function ($state, $record) {
                        if (blank($state) || !$record->linked_product_id) {
                            return "-";
                        }

                        $val = (float) $state;
                        $formatted = rtrim(
                            rtrim(number_format($val, 4, ",", "."), "0"),
                            ",",
                        );
                        if ($formatted === "") {
                            $formatted = "0";
                        }

                        return $formatted .
                            " " .
                            strtoupper(
                                $record->linkedProduct->unit_of_measure ?? "",
                            );
                    }),

                Tables\Columns\TextColumn::make("cost")
                    ->label("Custo da Porção")
                    ->state(function ($record) {
                        if (
                            !$record->linked_product_id ||
                            blank($record->linked_qty)
                        ) {
                            return null;
                        }

                        $unitCost = \App\Services\Catalog\ProductCostingService::calculateBaseCost(
                            $record->linkedProduct,
                        );

                        $portionCost = $unitCost * (float) $record->linked_qty;

                        return $portionCost;
                    })
                    ->money("BRL")
                    ->placeholder("-")
                    ->color("warning"),

                Tables\Columns\ToggleColumn::make("show_on_pos")
                    ->label("PDV / Salão"),

                Tables\Columns\ToggleColumn::make("show_on_delivery")
                    ->label("Delivery"),

                Tables\Columns\ToggleColumn::make("is_active")->label("Ativa"),
            ])
            ->defaultSort("sort_order")
            ->headerActions([Actions\CreateAction::make()])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
