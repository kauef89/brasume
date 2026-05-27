<?php

namespace App\Filament\Resources\Supplies\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Models\Category;
use Illuminate\Validation\Rules\Unique;

class SupplyForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make("Dados do Material")
                ->schema([
                    Forms\Components\TextInput::make("name")
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn(
                                Unique $rule,
                            ) => $rule->withoutTrashed(),
                        )
                        ->label("Descrição do Material")
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    Forms\Components\Select::make("category_id")
                        ->label("Categoria de Consumo")
                        ->options(
                            fn() => Category::where("type", "supply")->pluck(
                                "name",
                                "id",
                            ),
                        )
                        ->required()
                        ->searchable()
                        ->columnSpan(1),

                    Forms\Components\Select::make("type")
                        ->label("Classificação")
                        ->options([
                            "packaging" => "Embalagem",
                            "consumable" => "Uso Interno",
                        ])
                        ->required()
                        ->default("consumable")
                        ->columnSpan(1),

                    Forms\Components\Select::make("unit_of_measure")
                        ->label("Unidade de Medida")
                        ->options([
                            "UN" => "Unidade (UN)",
                            "CX" => "Caixa (CX)",
                            "KG" => "Quilograma (KG)",
                            "LT" => "Litro (LT)",
                        ])
                        ->required()
                        ->default("UN")
                        ->columnSpan(1),

                    Forms\Components\Toggle::make("track_inventory")
                        ->label("Controlar Estoque?")
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(1),

                    // 🚀 CAMPO INTELIGENTE: Abre para edição se não tiver histórico!
                    Forms\Components\TextInput::make("base_cost")
                        ->label("Custo Base")
                        ->prefix('R$')
                        ->formatStateUsing(function (
                            $state,
                            ?\App\Models\Product $record,
                        ) {
                            // 🚀 FORÇA EXIBIR O ÚLTIMO HISTÓRICO SE EXISTIR
                            if ($record) {
                                $latestHistory = \App\Models\ProductCostHistory::where(
                                    "product_id",
                                    $record->id,
                                )
                                    ->latest("id")
                                    ->first();
                                if ($latestHistory) {
                                    $state = $latestHistory->unit_cost;
                                }
                            }

                            if (blank($state)) {
                                return null;
                            }
                            $val = (float) $state;
                            $formatted = number_format($val, 5, ",", ".");
                            $formatted = rtrim($formatted, "0");
                            $formatted = rtrim($formatted, ",");
                            $parts = explode(",", $formatted);
                            if (count($parts) > 1) {
                                if (strlen($parts[1]) < 2) {
                                    $formatted =
                                        $parts[0] .
                                        "," .
                                        str_pad($parts[1], 2, "0");
                                }
                            } else {
                                $formatted .= ",00";
                            }
                            return $formatted;
                        })
                        ->readOnly(
                            fn(?\App\Models\Product $record) => $record &&
                                $record->costHistories()->exists(),
                        )
                        ->dehydrated(
                            fn(?\App\Models\Product $record) => !(
                                $record && $record->costHistories()->exists()
                            ),
                        )
                        ->mutateDehydratedStateUsing(function ($state) {
                            if (blank($state)) {
                                return 0;
                            }
                            $state = str_replace(".", "", (string) $state);
                            $state = str_replace(",", ".", $state);
                            return (float) $state;
                        })
                        ->helperText(function (?\App\Models\Product $record) {
                            if ($record && $record->costHistories()->exists()) {
                                return "Bloqueado. O sistema está extraindo o custo do seu Histórico de Compras.";
                            }
                            return "Sem histórico de notas. Você pode digitar o custo base inicial.";
                        })
                        ->suffix(function ($record) {
                            if (!$record) {
                                return "";
                            }
                            $lastPurchase = \App\Models\ProductCostHistory::where(
                                "product_id",
                                $record->id,
                            )
                                ->latest()
                                ->first();
                            $uom =
                                $lastPurchase?->original_unit_of_measure ??
                                $record->unit_of_measure;
                            return $uom ? " /" . strtolower($uom) : "";
                        })
                        ->columnSpan(2),
                ])
                ->columns(4)
                ->columnSpanFull(),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
