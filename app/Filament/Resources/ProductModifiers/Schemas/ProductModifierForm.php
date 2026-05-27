<?php

namespace App\Filament\Resources\ProductModifiers\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components\Utilities\Get;

class ProductModifierForm
{
    /**
     * @return array<int,SchemaComponents\Section>
     */
    public static function getFormSchema(): array
    {
        return [
            // SEÇÃO 1: IDENTIFICAÇÃO
            SchemaComponents\Section::make("Identificação do Grupo")
                ->schema([
                    FormComponents\TextInput::make("name")
                        ->label("Nome Interno (Filament)")
                        ->required(),

                    FormComponents\TextInput::make("display_name")
                        ->label("Nome no Cardápio (Frontend)")
                        ->required(),

                    FormComponents\Select::make("type")
                        ->label("Tipo de Modificador")
                        ->options([
                            "observation" => "Observação",
                            "add_on" => "Adicional",
                            "ingredient" => "Ingrediente",
                        ])
                        ->required()
                        ->live(),

                    FormComponents\Toggle::make("is_active")
                        ->label("Ativo?")
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(4)
                ->columnSpanFull(),

            // SEÇÃO 2: REGRAS E COMPORTAMENTO
            SchemaComponents\Section::make("Regras de Seleção")
                ->schema([
                    SchemaComponents\Grid::make(2)->schema([
                        FormComponents\Select::make("pricing_strategy")
                            ->label("Estratégia de Precificação")
                            ->options([
                                "fixed_increment" =>
                                    "Fixo (A): Apenas Incremento",
                                "base_plus_increment" =>
                                    "Soma (B): Preço Base + Incremento",
                                "conditional_increment" =>
                                    "Condicional (C): Base ou Incremento (se 0)",
                            ])
                            ->default("base_plus_increment")
                            ->visible(
                                fn(Get $get) => $get("type") === "ingredient",
                            )
                            ->required(),

                        FormComponents\Select::make("display_order_strategy")
                            ->label("Ordem de Exibição")
                            ->options([
                                "alphabetical" => "Alfabética",
                                "price_asc" => "Crescente por preço",
                                "price_desc" => "Decrescente por preço",
                                "arbitrary" => "Ordem arbitrária (Manual)",
                            ])
                            ->default("alphabetical")
                            ->required(),
                    ]),

                    SchemaComponents\Grid::make(5)->schema([
                        FormComponents\TextInput::make("min_selections")
                            ->label("Mínimo")
                            ->numeric()
                            ->default(0)
                            ->required(),
                        FormComponents\TextInput::make("max_selections")
                            ->label("Máximo")
                            ->numeric(),
                        FormComponents\TextInput::make("max_free")
                            ->label("Máx. Grátis")
                            ->numeric()
                            ->default(0)
                            ->visible(
                                fn(Get $get) => $get("type") === "ingredient",
                            ),
                        FormComponents\TextInput::make("increment")
                            ->label("Valor Extra (Un)")
                            ->numeric()
                            ->prefix('R$')
                            ->default(0)
                            ->visible(
                                fn(Get $get) => $get("type") === "ingredient",
                            ),
                        FormComponents\Toggle::make("allow_quantity")
                            ->label("Permitir Qtd?")
                            ->inline(false),
                    ]),
                ])
                ->columnSpanFull(),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
