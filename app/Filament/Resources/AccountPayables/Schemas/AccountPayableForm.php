<?php

namespace App\Filament\Resources\AccountPayables\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Toggle;

class AccountPayableForm
{
    public static function getFormSchema(bool $isModal = false): array
    {
        // Agrupamos os campos em variáveis para podermos reutilizá-los
        $abaDetalhes = [
            TextInput::make("description")
                ->label("Descrição")
                ->required()
                ->placeholder("Ex: Conta de Luz, Pagamento Freelancer")
                ->columnSpanFull(),
            ToggleButtons::make("beneficiary_type")
                ->label("Tipo de Destinatário")
                ->options([
                    "supplier" => "Fornecedor",
                    "employee" => "Colaborador",
                ])
                ->icons([
                    "supplier" => "heroicon-o-building-office",
                    "employee" => "heroicon-o-user",
                ])
                ->colors(["supplier" => "primary", "employee" => "info"])
                ->inline()
                ->default("supplier")
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function ($component, $record) {
                    if ($record) {
                        $component->state(
                            $record->employee_id ? "employee" : "supplier",
                        );
                    }
                })
                ->afterStateUpdated(function ($set) {
                    $set("supplier_id", null);
                    $set("employee_id", null);
                })
                ->columnSpanFull(),
            Grid::make(4)->schema([
                Select::make("supplier_id")
                    ->label("Fornecedor Vinculado")
                    ->options(fn() => \App\Models\Supplier::pluck("name", "id"))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (
                        $state,
                        \Filament\Forms\Set $set,
                    ) {
                        if (
                            $state &&
                            ($supplier = \App\Models\Supplier::find($state))
                        ) {
                            if ($supplier->accounting_category) {
                                $set(
                                    "accounting_category",
                                    $supplier->accounting_category,
                                );
                                $set(
                                    "accounting_subcategory",
                                    $supplier->accounting_subcategory,
                                );
                            }
                        }
                    })
                    ->createOptionForm([
                        \Filament\Schemas\Components\Section::make(
                            "Novo Fornecedor",
                        )->schema(
                            \App\Filament\Resources\Suppliers\Schemas\SupplierForm::getFormSchema(),
                        ),
                    ])
                    ->createOptionUsing(
                        fn(array $data) => \App\Models\Supplier::create($data)
                            ->id,
                    )
                    ->visible(
                        fn($get) => $get("beneficiary_type") === "supplier",
                    )
                    ->columnSpan(2),
                Select::make("employee_id")
                    ->label("Colaborador Vinculado")
                    ->options(fn() => \App\Models\Employee::pluck("name", "id"))
                    ->searchable()
                    ->visible(
                        fn($get) => $get("beneficiary_type") === "employee",
                    )
                    ->columnSpan(2),
                TextInput::make("amount")
                    ->label('Valor (R$)')
                    ->required()
                    ->numeric()
                    ->prefix('R$')
                    ->columnSpan(1),
                TextInput::make("installment")
                    ->label("Parcela")
                    ->placeholder("Ex: 1/3")
                    ->columnSpan(1),
            ]),
        ];

        $abaDRE = [
            Toggle::make("is_personal")
                ->label("Despesa Pessoal / Particular?")
                ->helperText(
                    "Marque se este valor não faz parte dos custos operacionais da empresa.",
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

        $abaVencimento = [
            Grid::make(3)->schema([
                DatePicker::make("due_date")
                    ->label("Data de Vencimento")
                    ->required(),
                Select::make("status")
                    ->label("Status")
                    ->options([
                        "pending" => "Pendente",
                        "paid" => "Pago",
                        "cancelled" => "Cancelado",
                    ])
                    ->default("pending")
                    ->required()
                    ->live(),
                DatePicker::make("payment_date")
                    ->label("Data de Pagamento")
                    ->visible(fn($get) => $get("status") === "paid")
                    ->required(fn($get) => $get("status") === "paid"),
            ]),
        ];

        $abaAnexos = [
            FileUpload::make("document_path")
                ->label("Anexo (Boleto PDF ou Recibo)")
                ->directory("payables_documents")
                ->acceptedFileTypes(["application/pdf", "image/*"])
                ->openable()
                ->downloadable()
                ->columnSpanFull(),
            Textarea::make("observations")
                ->label("Observações")
                ->rows(3)
                ->columnSpanFull(),
        ];

        // 🚀 SE FOR MODAL DE CONCILIAÇÃO: Retorna blocos verticais sanfonados (Compactos)
        if ($isModal) {
            return [
                Section::make("Detalhes da Conta")->schema($abaDetalhes),
                Section::make("DRE e Categoria")->schema($abaDRE)->collapsed(),
                Section::make("Vencimento e Pagamento")
                    ->schema($abaVencimento)
                    ->collapsed(),
                Section::make("Anexos e Observações")
                    ->schema($abaAnexos)
                    ->collapsed(),
            ];
        }

        // 🚀 SE FOR TELA OFICIAL: Retorna o layout luxuoso em Abas
        return [
            Tabs::make("AccountPayableTabs")
                ->tabs([
                    Tabs\Tab::make("Detalhes da Conta")
                        ->icon("heroicon-o-document-text")
                        ->schema($abaDetalhes),
                    Tabs\Tab::make("DRE e Categoria")
                        ->icon("heroicon-o-tag")
                        ->schema($abaDRE),
                    Tabs\Tab::make("Vencimento e Pagamento")
                        ->icon("heroicon-o-calendar-days")
                        ->schema($abaVencimento),
                    Tabs\Tab::make("Anexos e Observações")
                        ->icon("heroicon-o-paper-clip")
                        ->schema($abaAnexos),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
