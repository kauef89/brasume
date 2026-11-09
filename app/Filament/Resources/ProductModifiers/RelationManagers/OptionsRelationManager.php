<?php

namespace App\Filament\Resources\ProductModifiers\RelationManagers;

use App\Filament\Resources\Ingredients\Schemas\IngredientForm;
use App\Models\Product;
use Filament\Actions;
use Filament\Forms\Components as FormComponents;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = "options";
    protected static ?string $title = "Opções do Grupo";

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            FormComponents\TextInput::make("name")
                ->label("Nome da Opção")
                ->placeholder("Ex: Sem Cebola, ou Bacon Extra, ou Carne Moída")
                ->required()
                ->maxLength(255)
                ->columnSpan(2),

            FormComponents\TextInput::make("price")
                ->label('Preço Adicional (R$)')
                ->numeric()
                ->default(0)
                ->required()
                ->columnSpan(1),

            FormComponents\TextInput::make("max_quantity")
                ->label("Qtd. Máxima")
                ->numeric()
                ->default(1)
                ->helperText("Limite por pedido")
                ->required()
                ->columnSpan(1),

            // CASO A: ADICIONAIS / RECHEIOS (DEBITAM DO ESTOQUE)
            FormComponents\Select::make("linked_product_id")
                ->label("Adicional: Debitar Insumo do Estoque")
                ->relationship(
                    name: "linkedProduct",
                    titleAttribute: "name",
                    modifyQueryUsing: fn (Builder $query) => $query->whereIn("type", ["ingredient", "manufactured"]),
                )
                ->searchable()
                ->preload()
                ->live()
                ->helperText("Use quando esta opção adicionar insumo (ex: Queijo Extra ou Recheio de Pastel).")
                ->columnSpan(2),

            FormComponents\TextInput::make("linked_qty")
                ->label("Porção a Debitar")
                ->numeric()
                ->step("0.0001")
                ->visible(fn (Get $get) => filled($get("linked_product_id")))
                ->suffix(fn (Get $get) => Product::find($get("linked_product_id"))?->unit_of_measure)
                ->columnSpan(2),

            // CASO B: OBSERVAÇÕES / REMOÇÕES (ANULAM A BAIXA DA FICHA TÉCNICA)
            FormComponents\Select::make("removes_ingredient_id")
                ->label("Omissão: Anular Insumo da Ficha Técnica (Sem isto)")
                ->relationship(
                    name: "removesIngredient",
                    titleAttribute: "name",
                    modifyQueryUsing: fn (Builder $query) => $query->whereIn("type", ["ingredient", "manufactured"]),
                )
                ->searchable()
                ->preload()
                ->helperText("Se o cliente pedir este item (Ex: 'Sem Cebola'), o insumo selecionado NÃO será baixado no Kardex.")
                ->columnSpan(4),

            FormComponents\TextInput::make("sort_order")
                ->label("Ordem de Exibição")
                ->numeric()
                ->default(0)
                ->columnSpan(1),

            FormComponents\Toggle::make("show_on_pos")
                ->label("PDV / Salão")
                ->default(true)
                ->inline(false)
                ->columnSpan(1),

            FormComponents\Toggle::make("show_on_customer_app")
                ->label("Delivery")
                ->default(true)
                ->inline(false)
                ->columnSpan(1),

            FormComponents\Toggle::make("is_active")
                ->label("Ativa?")
                ->default(true)
                ->inline(false)
                ->columnSpan(1),
        ])->columns(4);
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
                    ->label("Opção")
                    ->searchable()
                    ->weight("bold"),

                Tables\Columns\TextColumn::make("price")
                    ->label("Preço Extra")
                    ->money("BRL")
                    ->sortable(),

                Tables\Columns\TextColumn::make("operation_type")
                    ->label("Comportamento no Kardex")
                    ->state(function ($record) {
                        if ($record->removes_ingredient_id) {
                            return '🚫 Anula: ' . ($record->removesIngredient?->name ?? 'Insumo');
                        }
                        if ($record->linked_product_id) {
                            $qty = (float) ($record->linked_qty ?? 1);
                            $uom = strtoupper($record->linkedProduct?->unit_of_measure ?? '');
                            return "➕ Debita: {$qty} {$uom} ({$record->linkedProduct?->name})";
                        }
                        return '⚪ Informativo (Sem baixa)';
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($record->removes_ingredient_id) return 'danger';
                        if ($record->linked_product_id) return 'success';
                        return 'gray';
                    }),

                Tables\Columns\TextColumn::make("portion_cost")
                    ->label("Custo Unit.")
                    ->state(function ($record) {
                        if ($record->linked_product_id && $record->linkedProduct) {
                            $unitCost = \App\Services\Catalog\ProductCostingService::calculateBaseCost($record->linkedProduct);
                            return (float) $record->linked_qty * $unitCost;
                        }
                        if ($record->removes_ingredient_id && $record->removesIngredient) {
                            return \App\Services\Catalog\ProductCostingService::calculateBaseCost($record->removesIngredient);
                        }
                        return null;
                    })
                    ->money("BRL")
                    ->placeholder("-")
                    ->color(fn ($record) => $record->removes_ingredient_id ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make("historical_weight")
                    ->label("Demanda Recente")
                    ->numeric(0)
                    ->suffix(" pedidos")
                    ->badge()
                    ->color("info")
                    ->sortable(),

                Tables\Columns\ToggleColumn::make("is_active")->label("Ativa"),
            ])
            ->defaultSort("sort_order")
            ->headerActions([Actions\CreateAction::make()])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
