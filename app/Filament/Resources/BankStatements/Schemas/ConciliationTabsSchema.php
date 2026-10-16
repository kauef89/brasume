<?php

namespace App\Filament\Resources\BankStatements\Schemas;

use App\Models\AccountPayable;
use App\Models\PaymentBatch;
use App\Models\RecurringExpense;
use App\Models\BankStatement;
use App\Models\Transaction;
use App\Models\Supplier;
use App\Models\BankAccount; // 🚀 Import necessário
use App\Filament\Resources\AccountPayables\Schemas\AccountPayableForm;
use App\Filament\Resources\RecurringExpenses\Schemas\RecurringExpenseForm;
use App\Services\Accounting\BankReconciliationService;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class ConciliationTabsSchema
{
    public static function make(BankStatement $statement): Group
        {
            $amountStr = number_format(abs((float) $statement->amount), 2, ',', '.');
            $colorClass = $statement->amount < 0 ? 'text-danger-600' : 'text-success-600';
            $dateStr = $statement->bank_date ? $statement->bank_date->format('d/m/Y') : '-';
            $descStr = $statement->counterparty_name ?: $statement->description;
            $docStr = $statement->document ? "<span class='ml-2 px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'>DOC: {$statement->document}</span>" : '';

            $calendarIcon = \Illuminate\Support\Facades\Blade::render("<x-heroicon-m-calendar class='w-4 h-4 mr-1.5 text-gray-400 shrink-0' />");

            $headerHtml = new HtmlString("
                <style>.fi-modal-window .fi-has-gap { gap: 1.5rem !important; }</style>
                <div class='flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 mb-2'>
                    <div class='flex flex-col'>
                        <span class='text-[10px] font-medium text-gray-400 uppercase tracking-wider mb-1'>Transação do Extrato</span>
                        <div class='text-sm font-semibold text-gray-900 dark:text-white flex items-center'>
                            {$calendarIcon}
                            <span>{$dateStr} &nbsp;&mdash;&nbsp; {$descStr} {$docStr}</span>
                        </div>
                    </div>
                    <div class='text-right flex flex-col items-end'>
                        <span class='text-[10px] font-medium text-gray-400 uppercase tracking-wider mb-1'>Valor</span>
                        <div class='text-lg font-medium {$colorClass}'>
                            R$ {$amountStr}
                        </div>
                    </div>
                </div>
            ");

            return Group::make([
                Placeholder::make('statement_header')->hiddenLabel()->content($headerHtml),
                \Filament\Forms\Components\Hidden::make('is_supplier_locked')->default(false)->dehydrated(false),

                ToggleButtons::make('conciliation_type')
                    ->label('1. Qual é a natureza desta transação?')
                    ->options([
                        'avulsa' => 'Despesa Avulsa / Lote',
                        'fixa' => 'Conta Fixa (Recorrente)',
                        'pessoal' => 'Despesa Pessoal / Sócio',
                        'transferencia' => 'Transferência / Pgto de Fatura',
                    ])
                    ->colors(['avulsa' => 'primary', 'fixa' => 'warning', 'pessoal' => 'info', 'transferencia' => 'success'])
                    ->icons(['avulsa' => 'heroicon-o-document-text', 'fixa' => 'heroicon-o-arrow-path', 'pessoal' => 'heroicon-o-user', 'transferencia' => 'heroicon-o-arrows-right-left'])
                    ->inline()->live()->required(),

                ToggleButtons::make('action_mode')
                    ->label('2. O que deseja fazer?')
                    ->options(['link' => 'Vincular a um registo pendente', 'new' => 'Lançar como novo registo'])
                    ->icons(['link' => 'heroicon-o-link', 'new' => 'heroicon-o-plus-circle'])
                    ->inline()->live()->required()
                    ->visible(fn(Get $get) => filled($get('conciliation_type')) && $get('conciliation_type') !== 'transferencia'),

                Section::make("Vincular a Despesa/Lote Pendente")
                    ->visible(fn(Get $get) => $get('conciliation_type') === 'avulsa' && $get('action_mode') === 'link')
                    ->schema([
                        Select::make("existing_payable_id")
                            ->hiddenLabel()->searchable()->placeholder("Busque por fornecedor, lote ou valor...")
                            ->options(function () {
                                $payables = AccountPayable::where("status", "pending")->whereNull("payment_batch_id")->where("is_personal", false)->get()
                                    ->mapWithKeys(fn($p) => ["payable_" . $p->id => "📄 " . ($p->supplier->name ?? ($p->employee->name ?? "S/F")) . " - R$ " . number_format($p->amount, 2, ",", ".")]);
                                $batches = PaymentBatch::where("status", "pending")->get()
                                    ->mapWithKeys(fn($b) => ["batch_" . $b->id => "📦 LOTE: " . $b->description . " - R$ " . number_format($b->total_amount, 2, ",", ".")]);
                                return collect($payables)->union($batches)->toArray();
                            }),
                    ]),
                Section::make("Lançar Nova Despesa")
                    ->visible(fn(Get $get) => $get('conciliation_type') === 'avulsa' && $get('action_mode') === 'new')
                    ->schema([ Group::make(AccountPayableForm::getFormSchema(true))->statePath('avulsa_new') ]),

                Section::make("Vincular a uma Conta Recorrente")
                    ->visible(fn(Get $get) => $get('conciliation_type') === 'fixa' && $get('action_mode') === 'link')
                    ->schema([
                        Select::make("existing_fixed_id")
                            ->hiddenLabel()->searchable()->placeholder("Selecione o Contrato / Assinatura")
                            ->options(RecurringExpense::where("is_active", 1)->get()->mapWithKeys(fn($f) => [$f->id => "🔄 " . ($f->supplier->name ?? "S/F") . " - " . $f->name])),
                    ]),
                Section::make("Cadastrar Nova Conta Recorrente")
                    ->visible(fn(Get $get) => $get('conciliation_type') === 'fixa' && $get('action_mode') === 'new')
                    ->schema([ Group::make(RecurringExpenseForm::getFormSchema(true))->statePath('fixa_new') ]),

                Section::make("Vincular a Despesa Pessoal Pendente")
                    ->visible(fn(Get $get) => $get('conciliation_type') === 'pessoal' && $get('action_mode') === 'link')
                    ->schema([
                        Select::make("existing_personal_id")
                            ->hiddenLabel()->searchable()->placeholder("Busque pelas suas despesas pendentes...")
                            ->options(fn() => AccountPayable::where("status", "pending")->where("is_personal", true)->get()->mapWithKeys(fn($p) => [$p->id => "👤 " . ($p->supplier->name ?? "S/F") . " - R$ " . number_format($p->amount, 2, ",", ".")])),
                    ]),
                Section::make("Lançar Nova Despesa Pessoal")
                    ->visible(fn(Get $get) => $get('conciliation_type') === 'pessoal' && $get('action_mode') === 'new')
                    ->schema([
                        Group::make([
                            Toggle::make('identify_supplier')->label('Deseja identificar a pessoa/empresa?')->live()->default(false)->hidden(fn(Get $get) => $get('is_supplier_locked')),
                            Select::make('supplier_id')->label('Favorecido')->options(Supplier::where('is_active', true)->pluck('name', 'id'))->visible(fn(Get $get) => $get('is_supplier_locked') || $get('identify_supplier'))->required(fn(Get $get) => $get('is_supplier_locked') || $get('identify_supplier'))->disabled(fn(Get $get) => $get('is_supplier_locked'))->dehydrated()->createOptionForm([Section::make("Novo Favorecido")->schema(\App\Filament\Resources\Suppliers\Schemas\SupplierForm::getFormSchema())])->createOptionAction(fn ($action) => $action->fillForm(['document' => $statement->document, 'name' => $statement->counterparty_name ?: $statement->description]))->createOptionUsing(fn(array $data) => Supplier::create($data)->id),
                            TextInput::make('description')->label('Descrição do Gasto'),
                        ])->statePath('pessoal_new')
                    ]),

                Section::make("Detalhes da Transferência")
                    ->visible(fn(Get $get) => $get('conciliation_type') === 'transferencia')
                    ->schema([
                        Group::make([
                            Select::make('target_account_id')
                                ->label('Conta de Destino / Origem')
                                ->helperText('Selecione o Cartão de Crédito ou a Conta Bancária que recebeu/enviou este valor.')
                                ->options(BankAccount::where('is_active', true)->where('id', '!=', $statement->bank_account_id)->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                            TextInput::make('description')
                                ->label('Descrição da Movimentação')
                                ->default(fn() => "Transferência: " . ($statement->counterparty_name ?: $statement->description))
                                ->required(),
                        ])->statePath('transfer_new')
                    ]),
            ]);
        }

        // 🚀 O CÓDIGO REESCRITO (AGORA DELEGA TUDO PARA O MOTOR!)
        public static function processConciliation(array $data, BankStatement $statement): void
        {
            $type = $data['conciliation_type'] ?? null;
            $mode = $data['action_mode'] ?? null;

            if (!$type && !empty($data['existing_payable_id'])) {
                $type = 'avulsa'; $mode = 'link';
            }

            $conciliable = null;
            $supplierId = null;
            $description = null;

            if ($type === 'transferencia') {
                $tData = $data['transfer_new'] ?? [];
                if (!empty($tData['target_account_id'])) {
                    $conciliable = BankAccount::find($tData['target_account_id']);
                    $description = $tData['description'] ?? "Transferência";
                }
            }
            elseif ($type === 'avulsa') {
                if ($mode === 'link' && !empty($data['existing_payable_id'])) {
                    $idRaw = $data["existing_payable_id"];
                    if (str_starts_with($idRaw, "batch_")) {
                        $conciliable = PaymentBatch::find(str_replace("batch_", "", $idRaw));
                    } else {
                        $conciliable = AccountPayable::find(str_replace("payable_", "", $idRaw));
                        $supplierId = $conciliable->supplier_id;
                    }
                } elseif ($mode === 'new' && !empty($data['avulsa_new']['amount'])) {
                    $cleanData = Arr::except($data['avulsa_new'], ['is_supplier_locked', 'is_status_locked']);
                    $conciliable = AccountPayable::create($cleanData);
                    $supplierId = $conciliable->supplier_id;
                }
            }
            elseif ($type === 'fixa') {
                if ($mode === 'link' && !empty($data['existing_fixed_id'])) {
                    $fixed = RecurringExpense::find($data["existing_fixed_id"]);
                    $desc = $fixed->name . " (" . $statement->bank_date->format("M/Y") . ")";
                    $conciliable = AccountPayable::create(["description" => $desc, "amount" => abs((float) $statement->amount), "supplier_id" => $fixed->supplier_id, "accounting_category" => $fixed->accounting_category, "accounting_subcategory" => $fixed->accounting_subcategory, "due_date" => $statement->bank_date]);
                    $supplierId = $fixed->supplier_id;
                } elseif ($mode === 'new' && !empty($data['fixa_new']['name'])) {
                    $fixed = RecurringExpense::create(Arr::except($data['fixa_new'], ['is_supplier_locked', 'is_status_locked']));
                    $conciliable = AccountPayable::create(["description" => $fixed->name . " (Inicial)", "amount" => abs((float) $statement->amount), "supplier_id" => $fixed->supplier_id, "accounting_category" => $fixed->accounting_category, "accounting_subcategory" => $fixed->accounting_subcategory, "due_date" => $statement->bank_date]);
                    $supplierId = $fixed->supplier_id;
                }
            }
            elseif ($type === 'pessoal') {
                if ($mode === 'link' && !empty($data['existing_personal_id'])) {
                    $conciliable = AccountPayable::find($data['existing_personal_id']);
                    $supplierId = $conciliable->supplier_id;
                } elseif ($mode === 'new') {
                    $pData = $data['pessoal_new'] ?? [];
                    $supplierId = !empty($pData['is_supplier_locked']) || (!empty($pData['identify_supplier']) && !empty($pData['supplier_id'])) ? $pData['supplier_id'] : Supplier::firstOrCreate(['name' => 'Uso Pessoal / Sócios'], ['type' => 'physical', 'is_personal' => true, 'accounting_category' => 'socios', 'accounting_subcategory' => 'pessoal', 'is_active' => true])->id;
                    $desc = !empty($pData['description']) ? $pData['description'] . " - " . $statement->counterparty_name : "Uso Pessoal: " . ($statement->counterparty_name ?? $statement->description);
                    $conciliable = AccountPayable::create(["description" => $desc, "supplier_id" => $supplierId, "amount" => abs((float) $statement->amount), "due_date" => $statement->bank_date, "is_personal" => true, "accounting_category" => "socios", "accounting_subcategory" => "pessoal"]);
                }
            }

            // 🚀 O TOQUE FINAL DE MESTRE: O MOTOR TRATA DE TUDO!
            if ($conciliable) {
                BankReconciliationService::finalize($statement, $conciliable, $supplierId, $description);
            }
        }
    }
