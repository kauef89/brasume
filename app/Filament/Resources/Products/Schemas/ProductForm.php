<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Set; // 🚀 Para manipular o estado interno
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use App\Models\Category;
use App\Models\Product;

class ProductForm
{
    public static function getFormSchema(): array
    {
        return [
            Tabs::make("ProductTabs")
                ->tabs([
                    // ==========================================
                    // ABA 1: INFORMAÇÕES BÁSICAS
                    // ==========================================
                    Tabs\Tab::make("Básico")
                        ->icon("heroicon-o-information-circle")
                        ->schema([
                            Grid::make(3)->schema([
                                Forms\Components\TextInput::make("name")
                                    ->label("Nome do Produto")
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Forms\Components\Select::make("status")
                                    ->label("Status")
                                    ->options([
                                        "available" => "Disponível",
                                        "out_of_stock" => "Esgotado",
                                        "archived" => "Arquivado",
                                    ])
                                    ->default("available")
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make("category_id")
                                    ->label("Categoria")
                                    ->required()
                                    ->relationship(
                                        name: "category",
                                        titleAttribute: "name",
                                        modifyQueryUsing: fn(
                                            $query,
                                        ) => $query->where("type", "external"),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make("name")
                                            ->label("Nome da Nova Categoria")
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $data["type"] = "external";
                                        return Category::create($data)->id;
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Select::make("type")
                                    ->label("Tipo")
                                    ->options([
                                        "resale" => "Revenda",
                                        "dish" => "Prato / Fabricação Própria",
                                        "combo" => "Combo",
                                    ])
                                    ->required()
                                    ->live() // Avisa o formulário para reagir a esta mudança
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make(
                                    "serves_people",
                                )
                                    ->label("Serve quantas pessoas?")
                                    ->numeric()
                                    ->columnSpan(1),
                            ]),

                            Grid::make(2)->schema([
                                Forms\Components\Textarea::make("description")
                                    ->label("Descrição (Cardápio)")
                                    ->rows(4)
                                    ->columnSpan(1),

                                Forms\Components\FileUpload::make("image_path")
                                    ->label("Foto do Produto")
                                    ->image()
                                    ->directory("products")
                                    ->columnSpan(1),
                            ]),

                            Grid::make(3)->schema([
                                Forms\Components\Toggle::make("show_on_menu")
                                    ->label("Mostrar no PDV/Balcão?")
                                    ->default(true),
                                Forms\Components\Toggle::make(
                                    "show_on_delivery",
                                )
                                    ->label("Mostrar no Delivery?")
                                    ->default(true),
                                Forms\Components\Toggle::make(
                                    "is_age_restricted",
                                )
                                    ->label("Restrito para +18?")
                                    ->default(false),
                            ]),
                        ]),

                    // ==========================================
                    // ABA 2: PREÇOS E ESTOQUE
                    // ==========================================
                    Tabs\Tab::make("Preços e Estoque")
                        ->icon("heroicon-o-currency-dollar")
                        ->schema([
                            Section::make("Precificação")->schema([
                                Grid::make(4)->schema([
                                    Forms\Components\TextInput::make(
                                        "dine_in_price",
                                    )
                                        ->label("Preço Salão")
                                        ->numeric()
                                        ->prefix('R$'),
                                    Forms\Components\TextInput::make(
                                        "delivery_price",
                                    )
                                        ->label("Preço Delivery")
                                        ->numeric()
                                        ->prefix('R$'),
                                    Forms\Components\TextInput::make(
                                        "promo_price",
                                    )
                                        ->label("Preço Promocional")
                                        ->numeric()
                                        ->prefix('R$'),
                                    Forms\Components\TextInput::make(
                                        "base_cost",
                                    )
                                        ->label("Custo Médio")
                                        ->numeric()
                                        ->prefix('R$')
                                        ->readOnly()
                                        ->formatStateUsing(function (
                                            $state,
                                            ?\App\Models\Product $record,
                                        ) {
                                            if ($record) {
                                                // Garante que a tela de produtos também puxe a mesma inteligência do motor central
                                                $realCost = \App\Services\ProductCostingService::calculateBaseCost(
                                                    $record,
                                                );
                                                return number_format(
                                                    $realCost,
                                                    2,
                                                    ".",
                                                    "",
                                                );
                                            }
                                            return $state;
                                        })
                                        ->helperText(
                                            "Calculado via Ficha Técnica ou Histórico de Compras",
                                        ),
                                ]),
                            ]),

                            Section::make("Controle de Estoque")
                                ->visible(
                                    fn(Get $get) => $get("type") !== "combo",
                                ) // 🚀 Combo não tem controle de estoque próprio
                                ->schema([
                                    Grid::make(4)->schema([
                                        Forms\Components\TextInput::make("sku")
                                            ->label("SKU")
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make(
                                            "barcode",
                                        )
                                            ->label("Código de Barras (EAN)")
                                            ->maxLength(255),
                                        Forms\Components\Select::make(
                                            "unit_of_measure",
                                        )
                                            ->label("Unid. Medida")
                                            ->options([
                                                "UN" => "Unidade",
                                                "KG" => "Quilograma",
                                                "L" => "Litro",
                                                "G" => "Grama",
                                                "ML" => "Mililitro",
                                            ])
                                            ->default("UN")
                                            ->required(),
                                        Forms\Components\TextInput::make(
                                            "conversion_factor",
                                        )
                                            ->label("Fator de Conversão")
                                            ->numeric()
                                            ->default(1),
                                    ]),

                                    Grid::make(3)->schema([
                                        Forms\Components\Toggle::make(
                                            "track_inventory",
                                        )
                                            ->label("Controlar Estoque?")
                                            ->default(true)
                                            ->live(),
                                        Forms\Components\TextInput::make(
                                            "current_stock",
                                        )
                                            ->label("Estoque Atual")
                                            ->numeric()
                                            ->default(0)
                                            ->visible(
                                                fn(Get $get) => $get(
                                                    "track_inventory",
                                                ),
                                            ),
                                        Forms\Components\TextInput::make(
                                            "min_stock",
                                        )
                                            ->label("Estoque Mínimo")
                                            ->numeric()
                                            ->default(0)
                                            ->visible(
                                                fn(Get $get) => $get(
                                                    "track_inventory",
                                                ),
                                            ),
                                    ]),
                                ]),
                        ]),

                    // ==========================================
                    // ABA 3: COZINHA E PRODUÇÃO (Oculta para Combo)
                    // ==========================================
                    Tabs\Tab::make("Produção")
                        ->icon("heroicon-o-fire")
                        ->visible(fn(Get $get) => $get("type") !== "combo") // 🚀 MAGIA: Esconde a aba inteira se for Combo
                        ->schema([
                            Grid::make(2)->schema([
                                Forms\Components\Select::make("kitchen_id")
                                    ->label("Setor de Preparo (Cozinha)")
                                    ->relationship("kitchen", "name")
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make(
                                    "print_station_id",
                                )
                                    ->label("Impressora de Produção")
                                    ->relationship("printer", "name")
                                    ->searchable()
                                    ->preload(),
                            ]),
                            Grid::make(3)->schema([
                                Forms\Components\TextInput::make(
                                    "prep_time_minutes",
                                )
                                    ->label("Tempo de Preparo (Minutos)")
                                    ->numeric(),
                                Forms\Components\Toggle::make(
                                    "requires_preparation",
                                )
                                    ->label("Exige Preparo?")
                                    ->default(true),
                                Forms\Components\Toggle::make("is_grab_and_go")
                                    ->label("Grab & Go (Pronto na vitrine)?")
                                    ->default(false),
                            ]),
                        ]),

                    // ==========================================
                    // ABA 4: FISCAL E TRIBUTAÇÃO (Oculta para Combo)
                    // ==========================================
                    Tabs\Tab::make("Fiscal")
                        ->icon("heroicon-o-document-text")
                        ->visible(fn(Get $get) => $get("type") !== "combo") // 🚀 MAGIA: Esconde a aba inteira se for Combo
                        ->schema([
                            Grid::make(3)->schema([
                                Forms\Components\TextInput::make("fiscal_ncm")
                                    ->label("NCM")
                                    ->maxLength(10),
                                Forms\Components\TextInput::make("fiscal_cest")
                                    ->label("CEST")
                                    ->maxLength(10),
                                Forms\Components\TextInput::make("fiscal_cfop")
                                    ->label("CFOP Padrão")
                                    ->maxLength(5),
                                Forms\Components\TextInput::make(
                                    "fiscal_origin",
                                )
                                    ->label("Origem (0 a 8)")
                                    ->maxLength(2),
                                Forms\Components\TextInput::make("fiscal_csosn")
                                    ->label("CSOSN / CST")
                                    ->maxLength(4),
                                Forms\Components\Toggle::make("is_monofasico")
                                    ->label("Produto Monofásico?")
                                    ->default(false),
                            ]),
                        ]),

                    // ==========================================
                    // ABA 5: CONFIGURAÇÃO DE COMBO (DINÂMICA)
                    // ==========================================
                    Tabs\Tab::make("Passos do Combo")
                        ->icon("heroicon-o-square-3-stack-3d")
                        ->visible(fn(Get $get) => $get("type") === "combo") // 🚀 Só aparece se o tipo for "Combo"
                        ->schema([
                            Forms\Components\Repeater::make("comboGroups")
                                ->relationship()
                                ->label("")
                                ->schema([
                                    Forms\Components\TextInput::make("name")
                                        ->label(
                                            "Nome do Passo (Ex: Escolha a Bebida)",
                                        )
                                        ->required()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make(
                                        "min_choices",
                                    )
                                        ->label("Mín. Escolhas")
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make(
                                        "max_choices",
                                    )
                                        ->label("Máx. Escolhas")
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->columnSpan(1),

                                    // 🚀 REPEATER ANINHADO
                                    Forms\Components\Repeater::make("items")
                                        ->relationship()
                                        ->label("Itens Disponíveis")
                                        ->schema([
                                            // 🚀 CAMPO FANTASMA: Decide o que mostrar
                                            Forms\Components\Select::make(
                                                "selection_type",
                                            )
                                                ->label("Tipo de Inserção")
                                                ->options([
                                                    "product" =>
                                                        "Produto Específico",
                                                    "category" =>
                                                        "Categoria Inteira",
                                                ])
                                                ->default("product")
                                                ->live()
                                                ->dehydrated(false) // Impede de salvar essa string no banco
                                                ->afterStateHydrated(function (
                                                    $component,
                                                    $state,
                                                    $record,
                                                ) {
                                                    // Quando carrega do banco, descobre sozinho o que foi salvo
                                                    if ($record?->category_id) {
                                                        $component->state(
                                                            "category",
                                                        );
                                                    } else {
                                                        $component->state(
                                                            "product",
                                                        );
                                                    }
                                                })
                                                ->afterStateUpdated(function (
                                                    $state,
                                                    Set $set,
                                                ) {
                                                    // Limpa o valor do outro campo ao trocar de tipo, para não salvar lixo
                                                    if ($state === "product") {
                                                        $set(
                                                            "category_id",
                                                            null,
                                                        );
                                                    }
                                                    if ($state === "category") {
                                                        $set(
                                                            "product_id",
                                                            null,
                                                        );
                                                    }
                                                })
                                                ->columnSpan(1),

                                            // 🚀 OPÇÃO 1: PRODUTO ESPECÍFICO
                                            Forms\Components\Select::make(
                                                "product_id",
                                            )
                                                ->label("Qual Produto?")
                                                ->options(
                                                    fn() => Product::whereIn(
                                                        "type",
                                                        [
                                                            "dish",
                                                            "resale",
                                                            "manufactured",
                                                        ],
                                                    )->pluck("name", "id"),
                                                )
                                                ->searchable()
                                                ->preload()
                                                ->required(
                                                    fn(Get $get) => $get(
                                                        "selection_type",
                                                    ) === "product",
                                                )
                                                ->visible(
                                                    fn(Get $get) => $get(
                                                        "selection_type",
                                                    ) === "product",
                                                )
                                                ->columnSpan(2),

                                            // 🚀 OPÇÃO 2: CATEGORIA INTEIRA
                                            Forms\Components\Select::make(
                                                "category_id",
                                            )
                                                ->label("Qual Categoria?")
                                                ->options(
                                                    fn() => Category::where(
                                                        "type",
                                                        "external",
                                                    )->pluck("name", "id"),
                                                )
                                                ->searchable()
                                                ->preload()
                                                ->required(
                                                    fn(Get $get) => $get(
                                                        "selection_type",
                                                    ) === "category",
                                                )
                                                ->visible(
                                                    fn(Get $get) => $get(
                                                        "selection_type",
                                                    ) === "category",
                                                )
                                                ->columnSpan(2),

                                            Forms\Components\TextInput::make(
                                                "additional_price",
                                            )
                                                ->label(
                                                    "Preço Extra (Opcional)",
                                                )
                                                ->numeric()
                                                ->prefix('R$')
                                                ->default(0)
                                                ->columnSpan(1),
                                        ])
                                        ->columns(4)
                                        ->columnSpanFull()
                                        ->addActionLabel("+ Adicionar Opção")
                                        ->itemLabel(function (
                                            array $state,
                                        ): ?string {
                                            // Título dinâmico do item fechado no Acordeão
                                            if (!empty($state["product_id"])) {
                                                return "Produto: " .
                                                    Product::find(
                                                        $state["product_id"],
                                                    )?->name;
                                            }
                                            if (!empty($state["category_id"])) {
                                                return "Categoria: " .
                                                    Category::find(
                                                        $state["category_id"],
                                                    )?->name;
                                            }
                                            return null;
                                        })
                                        ->collapsible(),
                                ])
                                ->columns(4)
                                ->addActionLabel(
                                    "+ Adicionar Novo Passo ao Combo",
                                )
                                ->collapsible()
                                ->itemLabel(
                                    fn(array $state): ?string => $state[
                                        "name"
                                    ] ?? null,
                                ),
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
