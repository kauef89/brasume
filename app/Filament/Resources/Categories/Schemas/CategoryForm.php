<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Illuminate\Validation\Rules\Unique;

class CategoryForm
{
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Detalhes")
                ->schema([
                    FormComponents\TextInput::make("name")
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn(
                                Unique $rule,
                            ) => $rule->withoutTrashed(),
                        )
                        ->label("Nome")
                        ->required(),

                    FormComponents\Select::make("type")
                        ->label("Macro Área (Onde esta categoria aparece?)")
                        ->options([
                            "external" =>
                                "Vendas / Cardápio (Pratos, Bebidas, Combos)",
                            "internal" =>
                                "Cozinha / Estoque (Insumos, Pré-preparos)",
                            "supply" =>
                                "Operação (Embalagens, Material de Limpeza)",
                        ])
                        ->required()
                        ->default("external"),

                    FormComponents\Select::make("parent_id")
                        ->relationship("parent", "name")
                        ->label("Categoria Pai (Opcional)")
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            FormComponents\TextInput::make("name")
                                ->label("Nome")
                                ->required(),
                            FormComponents\Select::make("type")
                                ->label("Macro Área")
                                ->options([
                                    "external" =>
                                        "Vendas / Cardápio (Pratos, Bebidas, Combos)",
                                    "internal" =>
                                        "Cozinha / Estoque (Insumos, Pré-preparos)",
                                    "supply" =>
                                        "Operação (Embalagens, Material de Limpeza)",
                                ])
                                ->required()
                                ->default("external"),
                        ]),

                    FormComponents\Textarea::make("description")
                        ->label("Descrição")
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->columnSpanFull(),

            SchemaComponents\Section::make("Configurações")
                ->schema([
                    FormComponents\TextInput::make("sort_order")
                        ->label("Ordem")
                        ->numeric()
                        ->default(0),
                    FormComponents\Toggle::make("is_active")
                        ->label("Ativa?")
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
