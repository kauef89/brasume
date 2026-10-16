<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UnitOfMeasure;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Product;

class PriceAndStockSection
{
    public static function make(): Section
    {
        return Section::make("Preços e Estoque")
            ->icon("heroicon-o-currency-dollar")
            ->schema([
                Section::make("Precificação")->schema([
                    Grid::make(4)->schema([
                        Forms\Components\TextInput::make("dine_in_price")
                            ->label("Preço Salão")
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make("delivery_price")
                            ->label("Preço Delivery")
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make("promo_price")
                            ->label("Preço Promocional")
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make("base_cost")
                            ->label("Custo Médio")
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly()
                            ->formatStateUsing(function ($state, ?Product $record) {
                                if ($record) {
                                    $realCost = \App\Services\Catalog\ProductCostingService::calculateBaseCost($record);
                                    return number_format($realCost, 2, ".", "");
                                }
                                return $state;
                            })
                            ->helperText("Calculado via Ficha Técnica ou Histórico de Compras"),
                    ]),
                ]),

                Section::make("Controle de Estoque")
                    ->visible(fn(Get $get) => $get("type") !== "combo")
                    ->schema([
                        Grid::make(4)->schema([
                            Forms\Components\TextInput::make("sku")
                                ->label("SKU")
                                ->maxLength(255),
                            Forms\Components\TextInput::make("barcode")
                                ->label("Código de Barras (EAN)")
                                ->maxLength(255),
                            Forms\Components\Select::make("unit_of_measure")
                                ->label("Unid. Medida")
                                ->options(UnitOfMeasure::class)
                                ->default("UN")
                                ->required(),
                            Forms\Components\TextInput::make("conversion_factor")
                                ->label("Fator de Conversão")
                                ->numeric()
                                ->default(1),
                        ]),

                        // 🚀 AQUI: Expandimos a Grid para acomodar a nova inteligência de níveis!
                        Grid::make(5)->schema([
                            Forms\Components\Toggle::make("track_inventory")
                                ->label("Controlar Estoque?")
                                ->default(true)
                                ->live(),
                            Forms\Components\TextInput::make("current_stock")
                                ->label("Estoque Atual")
                                ->numeric()
                                ->default(0)
                                ->visible(fn(Get $get) => $get("track_inventory")),
                            Forms\Components\TextInput::make("min_stock")
                                ->label("Estoque Mínimo")
                                ->numeric()
                                ->default(0)
                                ->visible(fn(Get $get) => $get("track_inventory")),
                            Forms\Components\TextInput::make("max_stock")
                                ->label("Estoque Máximo")
                                ->helperText("Teto da lista de compras")
                                ->numeric()
                                ->default(0)
                                ->visible(fn(Get $get) => $get("track_inventory")),
                            Forms\Components\TextInput::make("par_level")
                                ->label("Nível Ideal (Produção)")
                                ->helperText("Exclusivo para preparo")
                                ->numeric()
                                ->default(0)
                                // 🚀 Só é exibido para itens de fabrico interno!
                                ->visible(fn(Get $get) => $get("type") === "manufactured"),
                        ]),
                    ]),
            ])
            ->columnSpanFull();
    }
}
