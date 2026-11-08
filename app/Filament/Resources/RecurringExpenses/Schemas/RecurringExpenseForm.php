<?php

namespace App\Filament\Resources\RecurringExpenses\Schemas;

use App\Enums\AccountingCategory;


use App\Enums\RecurringExpenseValueType;use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;

class RecurringExpenseForm
{
    public static function getFormSchema(bool $isModal = false): array
    {
        $abaIdentificacao = [
            Grid::make(4)->schema([
                TextInput::make("name")
                    ->label("Nome / Apelido da Conta")
                    ->required()
                    ->placeholder("Ex: Conta de Luz Matriz")
                    ->maxLength(255),
                Select::make("creditor_type")
                    ->label("Tipo de Credor")
                    ->options(["supplier" => "Fornecedor / Concessionária", "employee" => "Colaborador / Diarista"])
                    ->default("supplier")
                    ->live()
                    ->required(),
                Select::make("supplier_id")
                    ->visible(fn(Get $get) => $get("creditor_type") === "supplier")
                    ->disabled(fn(Get $get) => $get('is_supplier_locked'))
                    ->dehydrated()
                    ->label("Fornecedor (Concessionária)")
                    ->required()
                    ->options(fn() => \App\Models\Supplier::where('is_active', true)->pluck("name", "id"))
                    ->searchable()
                    // 🚀 O QUICK CREATE MÁGICO:
                    ->createOptionForm([
                        Section::make("Novo Fornecedor")
                            ->schema(\App\Filament\Resources\Suppliers\Schemas\SupplierForm::getFormSchema())
                    ])
                    ->createOptionUsing(
                        fn(array $data) => \App\Models\Supplier::create($data)->id
                    ),
                Select::make("employee_id")
                    ->label("Colaborador / Prestador")
                    ->options(fn() => \App\Models\Employee::where("is_active", true)->pluck("name", "id"))
                    ->searchable()
                    ->visible(fn(Get $get) => $get("creditor_type") === "employee")
                    ->required(fn(Get $get) => $get("creditor_type") === "employee"),
                TextInput::make("contract_number")
                    ->label("Cód. Cliente / Contrato")
                    ->maxLength(255)
                    ->placeholder("Ex: UC 12345678"),
                Toggle::make("is_active")
                    ->label("Conta Ativa")
                    ->default(true),

            ]),
        ];

        $abaDRE = [
            Toggle::make("is_personal")
                ->label("Despesa Pessoal / Particular?")
                ->helperText("Marque se esta conta não faz parte dos custos operacionais da empresa.",
                )
                ->live()
                ->inline(false)
                ->default(false)
                ->afterStateUpdated(function ($set, $state) {
                    if ($state) {
                        $set("accounting_category", AccountingCategory::PARTNERS->value);
                        $set("accounting_subcategory", "personal");
                    } else {
                        $set("accounting_category", null);
                        $set("accounting_subcategory", null);
                    }
                })
                ->columnSpanFull(),
            Grid::make(2)->schema([
                Select::make("accounting_category")
                    ->label("Categoria de Despesa")
                    ->options(\App\Services\Accounting\AccountingService::getCategories())
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
                        ) => \App\Services\Accounting\AccountingService::getSubcategories(
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
                    ->options(RecurringExpenseValueType::class)
                    ->default("variable")
                    ->live()
                    ->required(),
                TextInput::make("default_amount")
                    ->label('Valor Padrão (R$)')
                    ->numeric()
                    ->prefix('R$')
                    ->visible(fn($get) => $get("value_type") === "fixed")
                    ->required(fn($get) => $get("value_type") === "fixed"),
                Select::make("frequency")
                    ->label("Periodicidade")
                    ->options(\App\Enums\PaymentCycle::class)
                    ->default("monthly")
                    ->live()
                    ->required(),
                Select::make("due_weekday")
                    ->label("Dia da Semana")
                    ->options(\App\Enums\Weekday::numericOptions())
                    ->visible(fn(Get $get) => $get("frequency") === "weekly")
                    ->required(fn(Get $get) => $get("frequency") === "weekly"),
                TextInput::make("due_day")
                    ->visible(fn(Get $get) => $get("frequency") !== "weekly")
                    ->label("Dia do Vencimento")
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->helperText(fn(Get $get) => $get("frequency") === "biweekly" ? "Dia base (ex: 1. A parcela 2 será no dia 15)" : ""),
                Select::make("due_month")
                    ->label("Mês de Vencimento")
                    ->options([
                        1 => "Janeiro", 2 => "Fevereiro", 3 => "Março", 4 => "Abril",
                        5 => "Maio", 6 => "Junho", 7 => "Julho", 8 => "Agosto",
                        9 => "Setembro", 10 => "Outubro", 11 => "Novembro", 12 => "Dezembro"
                    ])
                    ->visible(fn(Get $get) => $get("frequency") === "yearly")
                    ->required(fn(Get $get) => $get("frequency") === "yearly")
                    ->required()
            ]),
        ];

        $abaStatus = [
            Grid::make(3)->schema([
                Select::make("account_holder")
                    ->label("Titular da Conta (Fatura)")
                    ->options([
                        "company" => "Empresa (CNPJ)",
                        "partner" => "Sócio",
                        "third_party" => "Terceiros",
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
                Textarea::make("notes")
                    ->label("Observações Internas")
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ];

        return [
            Section::make("Identificação")
                ->icon("heroicon-o-identification")
                ->schema($abaIdentificacao)
                ->columnSpanFull(), // 🚀 Força a seção a ocupar 100% da largura

            Section::make("DRE e Categoria")
                ->icon("heroicon-o-tag")
                ->schema($abaDRE)
                ->columnSpanFull(), // 🚀 Força a seção a ocupar 100% da largura

            Section::make("Cobrança e Valores")
                ->icon("heroicon-o-currency-dollar")
                ->schema($abaCobranca)
                ->columnSpanFull(), // 🚀 Força a seção a ocupar 100% da largura

            Section::make("Responsabilidade e Status")
                ->icon("heroicon-o-clipboard-document-check")
                ->schema($abaStatus)
                ->columnSpanFull(), // 🚀 Força a seção a ocupar 100% da largura
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
