<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Set;
use App\Models\Supplier;

class SupplierForm
{
    /**
     * @return array<int,SchemaComponents\Tabs>
     */
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Tabs::make("Tabs")
                ->tabs([
                    // ABA 1: CADASTRO BÁSICO
                    SchemaComponents\Tabs\Tab::make("Cadastro")->schema([
                    FormComponents\Hidden::make("is_formal")
                            ->default(true)
                            ->dehydrated(false),

                        SchemaComponents\Grid::make(3)->schema([
                            FormComponents\TextInput::make("document")
                                ->label("CNPJ / CPF")
                                ->required()
                                ->maxLength(20),

                            FormComponents\Select::make("type")
                                ->label("Tipo")
                                ->options([
                                    "physical" => "Físico",
                                    "virtual" => "Virtual",
                                ])
                                ->default("physical")
                                ->live()
                                ->required(),

                            // 🚀 Nossa flag de uso pessoal está aqui
                            FormComponents\Toggle::make("is_personal")
                                ->label("Uso Pessoal?")
                                ->helperText(
                                    "Despesas auto-conciliadas como uso dos sócios.",
                                )
                                ->default(false)
                                ->inline(false),
                        ]),

                        SchemaComponents\Grid::make(3)->schema([
                            FormComponents\TextInput::make("name")
                                ->label("Nome Fantasia / Apelido")
                                ->required()
                                ->maxLength(255),
                            FormComponents\TextInput::make("document_name")
                                ->label("Razão Social (Opcional)")
                                ->maxLength(255),
                            FormComponents\Select::make("parent_id")
                                ->label("Matriz (Opcional)")
                                ->options(fn() => Supplier::pluck("name", "id"))
                                ->searchable()
                                ->preload()
                                ->helperText(
                                    "Se for uma filial, selecione a matriz aqui.",
                                ),
                        ]),
                    ]),

                    // 🚀 ABA 2: DRE / CLASSIFICAÇÃO PADRÃO (NOVO!)
                    SchemaComponents\Tabs\Tab::make("DRE e Padrões")->schema([
                        SchemaComponents\Section::make(
                            "Classificação Contábil Padrão",
                        )
                            ->description(
                                "Sempre que você criar uma despesa para este fornecedor, o sistema vai sugerir estas categorias automaticamente.",
                            )
                            ->schema([
                                SchemaComponents\Grid::make(2)->schema([
                                    FormComponents\Select::make(
                                        "accounting_category",
                                    )
                                        ->label("Categoria Padrão")
                                        ->options(
                                            \App\Services\AccountingService::getCategories(),
                                        )
                                        ->live()
                                        ->afterStateUpdated(
                                            fn($set) => $set(
                                                "accounting_subcategory",
                                                null,
                                            ),
                                        ),

                                    FormComponents\Select::make(
                                        "accounting_subcategory",
                                    )
                                        ->label("Subcategoria Padrão")
                                        ->options(
                                            fn(
                                                $get,
                                            ) => \App\Services\AccountingService::getSubcategories(
                                                $get("accounting_category"),
                                            ),
                                        )
                                        ->disabled(
                                            fn($get) => !$get(
                                                "accounting_category",
                                            ),
                                        ),
                                ]),
                            ]),
                    ]),

                    // ABA 3: CONTATO E ENDEREÇO
                    SchemaComponents\Tabs\Tab::make(
                        "Contato e Endereço",
                    )->schema([
                        SchemaComponents\Grid::make(3)->schema([
                            FormComponents\TextInput::make("email")
                                ->label("E-mail")
                                ->email()
                                ->maxLength(255),
                            FormComponents\TextInput::make("phone")
                                ->label("Telefone / WhatsApp")
                                ->tel()
                                ->maxLength(20),
                            FormComponents\TextInput::make("pix_key")
                                ->label("Chave PIX")
                                ->maxLength(255),
                        ]),

                        FormComponents\TextInput::make("website")
                            ->label("Site / URL da Loja")
                            ->url()
                            ->prefix("https://")
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->visible(
                                fn(Get $get) => $get("type") === "virtual",
                            ),

                        SchemaComponents\Section::make("Localização")
                            ->visible(
                                fn(Get $get) => $get("type") === "physical",
                            )
                            ->schema([
                                SchemaComponents\Grid::make(4)->schema([
                                    FormComponents\TextInput::make("zip_code")
                                        ->label("CEP")
                                        ->maxLength(10),
                                    FormComponents\TextInput::make("street")
                                        ->label("Rua/Avenida")
                                        ->columnSpan(2),
                                    FormComponents\TextInput::make(
                                        "number",
                                    )->label("Número"),
                                ]),
                                SchemaComponents\Grid::make(3)->schema([
                                    FormComponents\TextInput::make(
                                        "neighborhood",
                                    )->label("Bairro"),
                                    FormComponents\TextInput::make(
                                        "city",
                                    )->label("Cidade"),
                                    FormComponents\TextInput::make("state")
                                        ->label("Estado (UF)")
                                        ->maxLength(2),
                                ]),
                            ]),
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
