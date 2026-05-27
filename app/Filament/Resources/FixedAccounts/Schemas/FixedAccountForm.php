<?php

namespace App\Filament\Resources\FixedAccounts\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;

class FixedAccountForm
{
    public static function getFormSchema(bool $isModal = false): array
    {
        $abaIdentificacao = [
            Grid::make(3)->schema([
                TextInput::make("name")
                    ->label("Nome / Apelido da Conta")
                    ->required()
                    ->placeholder("Ex: Conta de Luz Matriz")
                    ->maxLength(255),
                Select::make("supplier_id")
                    ->label("Fornecedor (Concessionária)")
                    ->options(fn() => \App\Models\Supplier::pluck("name", "id"))
                    ->searchable(),
                TextInput::make("contract_number")
                    ->label("Cód. Cliente / Contrato")
                    ->maxLength(255)
                    ->placeholder("Ex: UC 12345678"),
            ]),
        ];

        $abaDRE = [
            Toggle::make("is_personal")
                ->label("Despesa Pessoal / Particular?")
                ->helperText(
                    "Marque se esta conta não faz parte dos custos operacionais da empresa.",
                )
                ->live()
                ->inline(false)
                ->default(false)
                ->afterStateUpdated(function ($set, $state) {
                    if ($state) {
                        $set("accounting_category", "socios");
                        $set("accounting_subcategory", "pessoal");
                    } else {
                        $set("accounting_category", null);
                        $set("accounting_subcategory", null);
                    }
                })
                ->columnSpanFull(),
            Grid::make(2)->schema([
                Select::make("accounting_category")
                    ->label("Categoria de Despesa")
                    ->options(\App\Services\AccountingService::getCategories())
                    ->live()
                    ->afterStateUpdated(
                        fn($set) => $set("accounting_subcategory", null),
                    )
                    ->required(),
                Select::make("accounting_subcategory")
                    ->label("Subcategoria")
                    ->options(
                        fn(
                            $get,
                        ) => \App\Services\AccountingService::getSubcategories(
                            $get("accounting_category"),
                        ),
                    )
                    ->required()
                    ->disabled(fn($get) => !$get("accounting_category")),
            ]),
        ];

        $abaCobranca = [
            Grid::make(3)->schema([
                Select::make("value_type")
                    ->label("Tipo de Valor")
                    ->options([
                        "variable" => "Variável (Ex: Luz, Água)",
                        "fixed" => "Fixo (Ex: Aluguel, Internet)",
                    ])
                    ->default("variable")
                    ->live()
                    ->required(),
                TextInput::make("default_amount")
                    ->label('Valor Padrão (R$)')
                    ->numeric()
                    ->prefix('R$')
                    ->visible(fn($get) => $get("value_type") === "fixed")
                    ->required(fn($get) => $get("value_type") === "fixed"),
                TextInput::make("due_day")
                    ->label("Dia do Vencimento")
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->required()
                    ->helperText(
                        "Em qual dia do mês essa conta costuma vencer?",
                    ),
            ]),
        ];

        $abaStatus = [
            Grid::make(3)->schema([
                Select::make("account_holder")
                    ->label("Titular da Conta (Fatura)")
                    ->options([
                        "company" => "Empresa (CNPJ)",
                        "partner" => "Sócio",
                        "third_party" => "Terceiros / Antigo Dono",
                    ])
                    ->default("company")
                    ->required(),
                Select::make("paid_by")
                    ->label("Quem efetuará o pagamento?")
                    ->options([
                        "company" => "Empresa (Caixa/Banco)",
                        "partner" => "Sócio (Bolso Próprio)",
                        "third_party" => "Terceiros",
                    ])
                    ->default("company")
                    ->required(),
                Toggle::make("is_auto_debit")
                    ->label("Em Débito Automático?")
                    ->default(false)
                    ->inline(false),
            ]),
            Grid::make(1)->schema([
                Toggle::make("is_active")
                    ->label("Conta Ativa (Lançar mensalmente)")
                    ->default(true),
                Textarea::make("notes")
                    ->label("Observações Internas")
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ];

        if ($isModal) {
            return [
                Section::make("Identificação")->schema($abaIdentificacao),
                Section::make("DRE e Categoria")->schema($abaDRE)->collapsed(),
                Section::make("Cobrança e Valores")
                    ->schema($abaCobranca)
                    ->collapsed(),
                Section::make("Responsabilidade e Status")
                    ->schema($abaStatus)
                    ->collapsed(),
            ];
        }

        return [
            Tabs::make("FixedAccountTabs")
                ->tabs([
                    Tabs\Tab::make("Identificação")
                        ->icon("heroicon-o-identification")
                        ->schema($abaIdentificacao),
                    Tabs\Tab::make("DRE e Categoria")
                        ->icon("heroicon-o-tag")
                        ->schema($abaDRE),
                    Tabs\Tab::make("Cobrança e Valores")
                        ->icon("heroicon-o-currency-dollar")
                        ->schema($abaCobranca),
                    Tabs\Tab::make("Responsabilidade e Status")
                        ->icon("heroicon-o-clipboard-document-check")
                        ->schema($abaStatus),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
