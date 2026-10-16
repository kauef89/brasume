<?php

namespace App\Filament\Resources\AccountPayables\Schemas;

use App\Models\Employee;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class AccountPayableForm
{
    public static function getFormSchema(bool $isModal = false): array
    {
        $abaComprovativo = [
            Placeholder::make('bank_statement_info')
                ->hiddenLabel()
                // 🚀 CORREÇÃO 1: Removida a tipagem estrita. E verificação usando instanceof
                ->content(function ($record) {
                    if (!$record instanceof \App\Models\AccountPayable || !$record->bankStatement) return '';

                    $stmt = $record->bankStatement;
                    $val = number_format(abs($stmt->amount), 2, ',', '.');
                    $date = $stmt->bank_date->format('d/m/Y');
                    $icon = Blade::render('<x-heroicon-o-check-circle class="w-8 h-8 shrink-0" />');

                    return new HtmlString("
                        <div class='p-4 bg-success-50 dark:bg-success-900/30 rounded-xl border border-success-200 dark:border-success-800 text-success-800 dark:text-success-400 flex items-center gap-4 mb-4'>
                            {$icon}
                            <div>
                                <p class='font-medium text-sm uppercase tracking-wider'>Liquidado via Extrato Bancário</p>
                                <p class='text-xs mt-1 font-medium'>Transação: {$stmt->description} ({$stmt->counterparty_name}) | Data: {$date} | Valor: R$ {$val}</p>
                            </div>
                        </div>
                    ");
                })
                // 🚀 CORREÇÃO 2: Verificação segura no visible()
                ->visible(fn ($record) => $record instanceof \App\Models\AccountPayable && $record->bankStatement !== null)
                ->columnSpanFull(),
        ];

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
                // 🚀 CORREÇÃO 3: Garantir que não quebra se for um Extrato Bancário a inicializar os campos
                ->afterStateHydrated(function ($component, $record) {
                    if ($record instanceof \App\Models\AccountPayable) {
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
                    ->disabled(fn(Get $get) => $get('is_supplier_locked'))
                    ->dehydrated()
                    ->label("Fornecedor Vinculado")
                    ->options(fn() => \App\Models\Supplier::where('is_active', true)->pluck("name", "id"))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (
                        $state,
                        Set $set,
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
                    ->options(fn() => \App\Models\Employee::where('is_active', true)->pluck("name", "id"))
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

        $abaVencimento = [
            Grid::make(4)->schema([ // Mudamos para 4 colunas
                DatePicker::make("due_date")
                    ->label("Data de Vencimento")
                    ->required()
                    ->columnSpan(1),

                Select::make("status")
                    ->label("Status")
                    ->options([
                        "pending" => "Pendente",
                        "paid" => "Pago",
                        "cancelled" => "Cancelado",
                    ])
                    ->default("pending")
                    ->required()
                    ->live()
                    ->disabled(fn(Get $get) => $get('is_status_locked'))
                    ->dehydrated()
                    ->columnSpan(1),

                DatePicker::make("payment_date")
                    ->label("Data de Pagamento")
                    ->visible(fn($get) => $get("status") === "paid")
                    ->required(fn($get) => $get("status") === "paid")
                    ->disabled(fn(Get $get) => $get('is_status_locked'))
                    ->dehydrated()
                    ->columnSpan(1),

                // 🚀 NOVO CAMPO: Qual conta pagou?
                Select::make("bank_account_id")
                    ->label("Conta Bancária (Saída)")
                    ->options(fn () => \App\Models\BankAccount::where('is_active', true)->pluck('name', 'id'))
                    ->visible(fn($get) => $get("status") === "paid")
                    ->required(fn($get) => $get("status") === "paid")
                    ->searchable()
                    ->preload()
                    ->columnSpan(1),
            ]),
            Hidden::make('is_status_locked')->default(false)->dehydrated(false),
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

        if ($isModal) {
            return [
                Group::make($abaComprovativo),

                Section::make("Detalhes da Conta")->schema($abaDetalhes),
                Section::make("DRE e Categoria")->schema($abaDRE)->collapsed(),
                Section::make("Vencimento e Pagamento")->schema($abaVencimento)->collapsed(),
                Section::make("Anexos e Observações")->schema($abaAnexos)->collapsed(),
            ];
        }

        return [
            Group::make($abaComprovativo)->columnSpanFull(),

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
