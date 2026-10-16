<?php

namespace App\Filament\Resources\Ingredients\Schemas;


use App\Enums\UnitOfMeasure;use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\Rules\Unique;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;

class IngredientForm
{
    // ==========================================
    // 1. ÁRVORE DECLARATIVA (Limpa e Legível)
    // ==========================================
    public static function getFormSchema(): array
    {
        return [
            Section::make("Dados do Insumo / Preparo")
                ->schema([
                    Grid::make(3)->schema([
                        self::getNameField(),
                        self::getCategoryField(),
                    ]),

                    Grid::make(4)->schema([
                        self::getTypeField(),
                        self::getUnitOfMeasureField(),
                        self::getRecipeYieldField(),
                        self::getBaseCostField(),
                    ]),

                    Grid::make(3)->schema([self::getTrackInventoryField()]),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }

    // ==========================================
    // 2. CONSTRUTORES DE CAMPOS (Isolados)
    // ==========================================
    private static function getNameField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make("name")
            ->unique(
                ignoreRecord: true,
                modifyRuleUsing: fn(Unique $rule) => $rule->withoutTrashed(),
            )
            ->label("Nome do Insumo/Preparo")
            ->required()
            ->columnSpan(2);
    }

    private static function getCategoryField(): Forms\Components\Select
    {
        return Forms\Components\Select::make("category_id")
            ->label("Categoria de Insumo")
            ->options(
                // 🚀 Lista APENAS categorias internas de insumo/produção
                fn() => Category::where("type", "internal")->pluck("name", "id"),
        )
            ->required()
            ->searchable()
            ->preload()
            ->live()
            ->afterStateUpdated(function ($state, Set $set) {
                $category = Category::find($state);
                if ($category && str_contains(strtolower($category->name), "preparo interno")) {
                    $set("type", "manufactured");
                } else {
                    $set("type", "ingredient");
                }
            })
            ->columnSpan(1);
    }

    private static function getTypeField(): Forms\Components\Select
    {
        return Forms\Components\Select::make("type")
            ->label("Tipo de Item")
            ->options([
                "ingredient"   => "Insumo (Comprado)",
                "manufactured" => "Preparado (Receita Interna)",
            ])
            ->default("ingredient")
            ->required()
            ->live()
            ->columnSpan(1);
    }

    private static function getUnitOfMeasureField(): Forms\Components\Select
    {
        return Forms\Components\Select::make("unit_of_measure")
            ->label("Unidade de Medida")
            ->options(UnitOfMeasure::class)
            ->required()
            ->live()
            ->columnSpan(1);
    }

    private static function getRecipeYieldField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make("recipe_yield")
            ->label("Rendimento do Preparo")
            ->numeric()
            ->default(1.0)
            ->step(0.0001)
            ->hintIcon(
                "heroicon-m-information-circle",
                tooltip: "Ex: Faz 2.1kg mas rende 1.8kg. Insira 1.8",
            )
            ->hintColor("primary")
            ->visible(fn(Get $get) => $get("type") === "manufactured")
            ->suffixAction(self::getYieldCalculatorAction())
            ->columnSpan(1);
    }

    private static function getBaseCostField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make("base_cost")
            ->label("Custo Médio")
            ->prefix('R$')
            ->formatStateUsing(function ($state, ?Product $record) {
                if ($record) {
                    $realCost = \App\Services\Catalog\ProductCostingService::calculateBaseCost(
                        $record,
                    );
                    $formatted = rtrim(
                        rtrim(number_format($realCost, 5, ",", "."), "0"),
                        ",",
                    );
                    $parts = explode(",", $formatted);
                    return count($parts) > 1
                        ? (strlen($parts[1]) < 2
                            ? $parts[0] . "," . str_pad($parts[1], 2, "0")
                            : $formatted)
                        : $formatted . ",00";
                }
                return $state;
            })
            ->readOnly(
                fn(?Product $record, Get $get) => $get("type") ===
                    "manufactured" ||
                    ($record && $record->costHistories()->exists()),
            )
            ->dehydrated(
                fn(?Product $record, Get $get) => !(
                    $get("type") === "manufactured" ||
                    ($record && $record->costHistories()->exists())
                ),
            )
            ->mutateDehydratedStateUsing(function ($state) {
                if (blank($state)) {
                    return 0;
                }
                return (float) str_replace(
                    ",",
                    ".",
                    str_replace(".", "", (string) $state),
                );
            })
            ->hintIcon("heroicon-m-information-circle")
            ->hintColor("primary")
            ->hintIconTooltip(function (?Product $record, Get $get) {
                if ($get("type") === "manufactured") {
                    return "Calculado pela Ficha Técnica.";
                }
                if ($record && $record->costHistories()->exists()) {
                    return "Bloqueado. Extraído da Nota de Compra.";
                }
                return "Sem histórico. Digite o custo base inicial.";
            })
            ->columnSpan(1);
    }

    private static function getTrackInventoryField(): Forms\Components\Toggle
    {
        return Forms\Components\Toggle::make("track_inventory")
            ->label("Controlar Estoque Físico?")
            ->default(true)
            ->inline(false)
            ->columnSpan(1);
    }

    // ==========================================
    // 3. AÇÕES COMPLEXAS E REGRAS DE NEGÓCIO
    // ==========================================
    private static function getYieldCalculatorAction(): Action
    {
        return Action::make("calculadora_rendimento")
            ->icon("heroicon-m-calculator")
            ->tooltip("Calcular Rendimento por Regra de Três")
            ->color("primary")
            ->visible(
                fn(Get $get, ?Product $record) => $get("type") ===
                    "manufactured" &&
                    $get("unit_of_measure") === "UN" &&
                    $record !== null,
            )
            ->modalHeading("Calculadora de Rendimento")
            ->modalDescription(
                "O sistema vai somar os ingredientes da sua ficha técnica e calcular quantas unidades rendem.",
            )
            ->form(function (?Product $record) {
                $totalGrams = self::calculateTotalGramsFromSheet($record);

                if ($totalGrams == 0) {
                    return [
                        Forms\Components\Placeholder::make("aviso")
                            ->label("Atenção")
                            ->content(
                                "Nenhum ingrediente em Peso ou Volume encontrado. Salve os insumos na Ficha Técnica primeiro!",
                            ),
                    ];
                }

                $displayInput =
                    $totalGrams >= 1000
                        ? number_format($totalGrams / 1000, 3, ",", "") .
                            " kg / L"
                        : number_format($totalGrams, 0, "", "") . " g / ml";

                return [
                    Forms\Components\Placeholder::make("entrada_total")
                        ->label("Entrada Total (Ficha Técnica)")
                        ->content($displayInput),

                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make("unit_weight")
                            ->label("Peso de 1 Unidade")
                            ->numeric()
                            ->required()
                            ->live(onBlur: true),

                        Forms\Components\Select::make("weight_unit")
                            ->label("Medida")
                            ->options([
                                "g" => "Gramas (g)",
                                "kg" => "Quilos (kg)",
                            ])
                            ->default("g")
                            ->required()
                            ->live(),
                    ]),

                    Forms\Components\Placeholder::make("rendimento_calculado")
                        ->label("Rendimento")
                        ->content(function (Get $get) use ($totalGrams) {
                            $weight = (float) $get("unit_weight");
                            if ($weight <= 0) {
                                return "-";
                            }
                            $weightInGrams =
                                $get("weight_unit") === "kg"
                                    ? $weight * 1000
                                    : $weight;
                            $yield = floor($totalGrams / $weightInGrams);
                            return "{$yield} un (arredondado)";
                        }),
                ];
            })
            ->action(function (array $data, Set $set, ?Product $record) {
                if (!isset($data["unit_weight"])) {
                    return;
                }

                $totalGrams = self::calculateTotalGramsFromSheet($record);
                $weight = (float) $data["unit_weight"];

                if ($weight > 0) {
                    $weightInGrams =
                        $data["weight_unit"] === "kg"
                            ? $weight * 1000
                            : $weight;
                    $set("recipe_yield", floor($totalGrams / $weightInGrams));
                }
            });
    }

    // Helper: Verifica se a categoria é "Preparo Interno"
    private static function isManufacturedCategory($categoryId): bool
    {
        if (!$categoryId) {
            return false;
        }
        $category = Category::find($categoryId);
        return $category &&
            str_contains(strtolower($category->name), "preparo interno");
    }

    // Helper: Soma todo o peso da ficha técnica (evita código duplicado)
    private static function calculateTotalGramsFromSheet(
        ?Product $record,
    ): float {
        if (!$record) {
            return 0;
        }

        $totalGrams = 0;
        $sheetItems = \App\Models\TechnicalSheet::where(
            "parent_product_id",
            $record->id,
        )->get();

        foreach ($sheetItems as $item) {
            $child = Product::find($item->ingredient_product_id);
            if ($child) {
                $qty = (float) $item->quantity;
                $uom = strtoupper($child->unit_of_measure ?? "UN");
                if (in_array($uom, ["KG", "LT"])) {
                    $totalGrams += $qty * 1000;
                } elseif (in_array($uom, ["GR", "ML"])) {
                    $totalGrams += $qty;
                }
            }
        }
        return $totalGrams;
    }
}
