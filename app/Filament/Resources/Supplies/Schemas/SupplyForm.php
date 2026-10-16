<?php

namespace App\Filament\Resources\Supplies\Schemas;

use App\Enums\CategoryType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UnitOfMeasure;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use App\Models\Category;
use Illuminate\Validation\Rules\Unique;

class SupplyForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make("Dados do Material")
                ->schema([
                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make("name")
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn(Unique $rule) => $rule->withoutTrashed(),
                            )
                            ->label("Descrição do Material")
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\Select::make("category_id")
                            ->label("Categoria de Consumo")
                            ->options(fn() => Category::where("type", CategoryType::SUPPLY->value)->pluck("name", "id"))
                            ->required()
                            ->searchable()
                            ->columnSpan(1),
                    ]),

                    Grid::make(4)->schema([
                        Forms\Components\Select::make("type")
                            ->label("Classificação")
                            ->options([
                                ProductType::PACKAGING->value  => ProductType::PACKAGING->getLabel(),
                                ProductType::CONSUMABLE->value => ProductType::CONSUMABLE->getLabel(),
                            ])
                            ->required()
                            ->default(ProductType::CONSUMABLE->value)
                            ->columnSpan(1),

                        Forms\Components\Select::make("status")
                            ->label("Disponibilidade")
                            ->options(ProductStatus::class)
                            ->default(ProductStatus::AVAILABLE->value)
                            ->required()
                            ->selectablePlaceholder(false)
                            ->columnSpan(1),

                        Forms\Components\Select::make("unit_of_measure")
                            ->label("Unidade de Medida")
                            ->options(UnitOfMeasure::class)
                            ->required()
                            ->default(UnitOfMeasure::UN->value)
                            ->columnSpan(1),

                        Forms\Components\Toggle::make("track_inventory")
                            ->label("Controlar Estoque?")
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),
                    ]),

                    Forms\Components\TextInput::make("base_cost")
                        ->label("Custo Base")
                        ->prefix('R$')
                        ->formatStateUsing(function ($state, ?\App\Models\Product $record) {
                            if ($record) {
                                $latestHistory = \App\Models\ProductCostHistory::where("product_id", $record->id)
                                    ->latest("id")
                                    ->first();
                                if ($latestHistory) {
                                    $state = $latestHistory->unit_cost;
                                }
                            }

                            if (blank($state)) return null;

                            $val = (float) $state;
                            $formatted = rtrim(rtrim(number_format($val, 5, ",", "."), "0"), ",");
                            $parts = explode(",", $formatted);
                            return count($parts) > 1
                                ? (strlen($parts[1]) < 2 ? $parts[0] . "," . str_pad($parts[1], 2, "0") : $formatted)
                                : $formatted . ",00";
                        })
                        ->readOnly(fn(?\App\Models\Product $record) => $record && $record->costHistories()->exists())
                        ->dehydrated(fn(?\App\Models\Product $record) => !($record && $record->costHistories()->exists()))
                        ->mutateDehydratedStateUsing(function ($state) {
                            if (blank($state)) return 0;
                            return (float) str_replace(",", ".", str_replace(".", "", (string) $state));
                        })
                        ->helperText(function (?\App\Models\Product $record) {
                            if ($record && $record->costHistories()->exists()) {
                                return "Bloqueado. O sistema está extraindo o custo do Histórico de Compras.";
                            }
                            return "Sem histórico de notas. Você pode digitar o custo base inicial.";
                        })
                        ->suffix(function ($record) {
                            if (!$record) return "";
                            $lastPurchase = \App\Models\ProductCostHistory::where("product_id", $record->id)->latest()->first();
                            $uom = $lastPurchase?->original_unit_of_measure ?? $record->unit_of_measure;
                            return $uom ? " /" . strtolower($uom) : "";
                        })
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
