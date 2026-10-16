<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Product;
use App\Models\Category;

class ComboSection
{
    public static function make(): Section
    {
        return Section::make("Passos do Combo")
            ->icon("heroicon-o-square-3-stack-3d")
            ->visible(fn(Get $get) => $get("type") === "combo")
            ->schema([
                Forms\Components\Repeater::make("comboGroups")
                    ->relationship()
                    ->label("")
                    ->schema([
                        Forms\Components\TextInput::make("name")
                            ->label("Nome do Passo (Ex: Escolha a Bebida)")
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make("min_choices")
                            ->label("Mín. Escolhas")
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make("max_choices")
                            ->label("Máx. Escolhas")
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Repeater::make("items")
                            ->relationship()
                            ->label("Itens Disponíveis")
                            ->schema([
                                Forms\Components\Select::make("selection_type")
                                    ->label("Tipo de Inserção")
                                    ->options([
                                        "product" => "Produto Específico",
                                        "category" => "Categoria Inteira",
                                    ])
                                    ->default("product")
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        if ($record?->category_id) {
                                            $component->state("category");
                                        } else {
                                            $component->state("product");
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state === "product") $set("category_id", null);
                                        if ($state === "category") $set("product_id", null);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make("product_id")
                                    ->label("Qual Produto?")
                                    ->options(fn() => Product::whereIn("type", ["dish", "resale", "manufactured"])->pluck("name", "id"))
                                    ->searchable()
                                    ->preload()
                                    ->required(fn(Get $get) => $get("selection_type") === "product")
                                    ->visible(fn(Get $get) => $get("selection_type") === "product")
                                    ->columnSpan(2),

                                Forms\Components\Select::make("category_id")
                                    ->label("Qual Categoria?")
                                    ->options(fn() => Category::where("type", "external")->pluck("name", "id"))
                                    ->searchable()
                                    ->preload()
                                    ->required(fn(Get $get) => $get("selection_type") === "category")
                                    ->visible(fn(Get $get) => $get("selection_type") === "category")
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make("additional_price")
                                    ->label("Preço Extra (Opcional)")
                                    ->numeric()
                                    ->prefix('R$')
                                    ->default(0)
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->columnSpanFull()
                            ->addActionLabel("+ Adicionar Opção")
                            ->itemLabel(function (array $state): ?string {
                                if (!empty($state["product_id"])) {
                                    return "Produto: " . Product::find($state["product_id"])?->name;
                                }
                                if (!empty($state["category_id"])) {
                                    return "Categoria: " . Category::find($state["category_id"])?->name;
                                }
                                return null;
                            })
                            ->collapsible(),
                    ])
                    ->columns(4)
                    ->addActionLabel("+ Adicionar Novo Passo ao Combo")
                    ->collapsible()
                    ->itemLabel(fn(array $state): ?string => $state["name"] ?? null),
            ])
            ->columnSpanFull();
    }
}
