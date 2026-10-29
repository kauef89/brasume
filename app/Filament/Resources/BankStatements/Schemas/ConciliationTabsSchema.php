<?php

namespace App\Filament\Resources\BankStatements\Schemas;

use App\Models\AccountPayable;
use App\Models\PaymentBatch;
use App\Models\RecurringExpense;
use App\Models\BankStatement;
use App\Models\BankReconciliationRule;
use App\Models\Transaction;
use App\Models\Supplier;
use App\Models\BankAccount;
use App\Enums\AccountingCategory;
use App\Enums\ConciliationType;
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
use Illuminate\Support\Arr;

class ConciliationTabsSchema
{
    public static function make(BankStatement $statement): Group
    {
        $amountStr = number_format(abs((float) $statement->amount), 2, ',', '.');
        $colorClass = $statement->amount < 0 ? 'text-danger-600' : 'text-success-600';
        $dateStr = $statement->bank_date ? $statement->bank_date->format('d/m/Y') : '-';
        $descStr = $statement->counterparty_name ?: $statement->description;
        $docStr = $statement->document ? "<span class='ml-2 px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'>DOC: {$statement->document}</span>" : '';

        // Deteção de Semelhantes no Extrato Pendente (mesmo sinal de débito/crédito)
        $cleanSearchTerm = trim((string) ($statement->counterparty_name ?: $statement->description));
        $sign = $statement->amount < 0 ? '<' : '>';

        $similarQuery = BankStatement::where('status', 'pending')
            ->where('id', '!=', $statement->id)
            ->where('amount', $sign, 0)
            ->where(function ($q) use ($statement, $cleanSearchTerm) {
                if (!empty($statement->document)) {
                    $q->where('document', $statement->document);
                }
                if (!empty($cleanSearchTerm) && strlen($cleanSearchTerm) >= 3) {
                    $q->orWhere('counterparty_name', 'like', "%{$cleanSearchTerm}%")
                      ->orWhere('description', 'like', "%{$cleanSearchTerm}%");
                }
            });

        $similarCount = $similarQuery->count();
        $similarSum = abs((float) $similarQuery->sum('amount'));

        $similarNoticeHtml = '';
        if ($similarCount > 0) {
            $sumFmt = number_format($similarSum, 2, ',', '.');
            $similarNoticeHtml = "
                <div class='mt-2.5 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2.5'>
                    <span class='text-base shrink-0'>⚡</span>
                    <div class='flex flex-col'>
                        <span class='font-bold leading-tight'>Identificámos mais {$similarCount} lançamento(s) pendente(s) desta mesma contraparte!</span>
                        <span class='text-[11px] opacity-80 mt-0.5'>Total acumulado dos semelhantes: R$ {$sumFmt}. Pode conciliá-los de uma vez abaixo.</span>
                    </div>
                </div>
            ";
        }

        $headerHtml = new HtmlString("
            <style>.fi-modal-window .fi-has-gap { gap: 1.5rem !important; }</style>
            <div class='flex flex-col p-4 rounded-xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 mb-2'>
                <div class='flex items-center justify-between'>
                    <div class='flex flex-col'>
                        <span class='text-[10px] font-medium text-gray-400 uppercase tracking-wider mb-1'>Transação do Extrato</span>
                        <div class='text-sm font-semibold text-gray-900 dark:text-white flex items-center'>
                            <span>{$dateStr} &nbsp;&mdash;&nbsp; {$descStr} {$docStr}</span>
                        </div>
                    </div>
                    <div class='text-right flex flex-col items-end'>
                        <span class='text-[10px] font-medium text-gray-400 uppercase tracking-wider mb-1'>Valor</span>
                        <div class='text-lg font-black {$colorClass}'>
                            R$ {$amountStr}
                        </div>
                    </div>
                </div>
                {$similarNoticeHtml}
            </div>
        ");

        return Group::make([
            Placeholder::make('statement_header')->hiddenLabel()->content($headerHtml),
            \Filament\Forms\Components\Hidden::make('is_supplier_locked')->default(false)->dehydrated(false),
            \Filament\Forms\Components\Hidden::make('search_term_used')->default($cleanSearchTerm),

            ToggleButtons::make('conciliation_type')
                ->label('1. Qual é a natureza desta transação?')
                ->options(ConciliationType::class)
                ->inline()
                ->live()
                ->required(),

            ToggleButtons::make('action_mode')
                ->label('2. O que deseja fazer?')
                ->options(['link' => 'Vincular a um registo pendente', 'new' => 'Lançar como novo registo'])
                ->icons(['link' => 'heroicon-o-link', 'new' => 'heroicon-o-plus-circle'])
                ->inline()->live()->required()
                ->visible(fn(Get $get) => filled($get('conciliation_type')) && $get('conciliation_type') !== ConciliationType::TRANSFER->value),

            // SEÇÃO: VINCULAR PENDENTE
            Section::make("Vincular a Despesa/Lote/Romaneio Pendente")
                ->visible(fn(Get $get) => $get('conciliation_type') === ConciliationType::EXPENSE->value && $get('action_mode') === 'link')
                ->schema([
                    Select::make("existing_payable_id")
                        ->hiddenLabel()->searchable()->placeholder("Busque por fornecedor, canhoto de romaneio ou valor...")
                        ->options(function () {
                            $payables = AccountPayable::where("status", "pending")->whereNull("payment_batch_id")->where("is_personal", false)->get()
                                ->mapWithKeys(fn($p) => ["payable_" . $p->id => "📄 " . ($p->supplier->name ?? ($p->employee->name ?? "S/F")) . " - R$ " . number_format($p->amount, 2, ",", ".") . " (" . $p->description . ")"]);
                            $batches = PaymentBatch::where("status", "pending")->get()
                                ->mapWithKeys(fn($b) => ["batch_" . $b->id => "📦 LOTE: " . $b->description . " - R$ " . number_format($b->total_amount, 2, ",", ".")]);
                            return collect($payables)->union($batches)->toArray();
                        }),
                ]),

            Section::make("Lançar Nova Despesa Operacional")
                ->visible(fn(Get $get) => $get('conciliation_type') === ConciliationType::EXPENSE->value && $get('action_mode') === 'new')
                ->schema([ Group::make(AccountPayableForm::getFormSchema(true))->statePath('avulsa_new') ]),

            Section::make("Vincular a uma Conta Recorrente")
                ->visible(fn(Get $get) => $get('conciliation_type') === ConciliationType::RECURRING->value && $get('action_mode') === 'link')
                ->schema([
                    Select::make("existing_fixed_id")
                        ->hiddenLabel()->searchable()->placeholder("Selecione o Contrato / Assinatura")
                        ->options(RecurringExpense::where("is_active", 1)->get()->mapWithKeys(fn($f) => [$f->id => "🔄 " . ($f->supplier->name ?? "S/F") . " - " . $f->name])),
                ]),

            Section::make("Cadastrar Nova Conta Recorrente")
                ->visible(fn(Get $get) => $get('conciliation_type') === ConciliationType::RECURRING->value && $get('action_mode') === 'new')
                ->schema([ Group::make(RecurringExpenseForm::getFormSchema(true))->statePath('fixa_new') ]),

            Section::make("Vincular a Despesa Pessoal Pendente")
                ->visible(fn(Get $get) => $get('conciliation_type') === ConciliationType::PERSONAL->value && $get('action_mode') === 'link')
                ->schema([
                    Select::make("existing_personal_id")
                        ->hiddenLabel()->searchable()->placeholder("Busque pelas suas despesas pendentes...")
                        ->options(fn() => AccountPayable::where("status", "pending")->where("is_personal", true)->get()->mapWithKeys(fn($p) => [$p->id => "👤 " . ($p->supplier->name ?? "S/F") . " - R$ " . number_format($p->amount, 2, ",", ".") . " ({$p->description})"])),
                ]),

            Section::make("Lançar Nova Despesa Pessoal")
                ->visible(fn(Get $get) => $get('conciliation_type') === ConciliationType::PERSONAL->value && $get('action_mode') === 'new')
                ->schema([
                    Group::make([
                        Toggle::make('identify_supplier')->label('Deseja identificar o favorecido/sócio?')->live()->default(false)->hidden(fn(Get $get) => $get('is_supplier_locked')),
                        Select::make('supplier_id')->label('Favorecido')->options(Supplier::where('is_active', true)->pluck('name', 'id'))->visible(fn(Get $get) => $get('is_supplier_locked') || $get('identify_supplier'))->required(fn(Get $get) => $get('is_supplier_locked') || $get('identify_supplier'))->disabled(fn(Get $get) => $get('is_supplier_locked'))->dehydrated()->createOptionForm([Section::make("Novo Favorecido")->schema(\App\Filament\Resources\Suppliers\Schemas\SupplierForm::getFormSchema())])->createOptionAction(fn ($action) => $action->fillForm(['document' => $statement->document, 'name' => $statement->counterparty_name ?: $statement->description]))->createOptionUsing(fn(array $data) => Supplier::create($data)->id),
                        TextInput::make('description')->label('Descrição do Gasto')->placeholder('Deixe vazio para manter a descrição do banco'),
                    ])->statePath('pessoal_new')
                ]),

            Section::make("Detalhes da Transferência")
                ->visible(fn(Get $get) => $get('conciliation_type') === ConciliationType::TRANSFER->value)
                ->schema([
                    Group::make([
                        Select::make('target_account_id')
                            ->label('Conta de Destino / Origem')
                            ->helperText('Selecione a conta que recebeu ou enviou o recurso.')
                            ->options(BankAccount::where('is_active', true)->where('id', '!=', $statement->bank_account_id)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('description')
                            ->label('Descrição da Movimentação')
                            ->default(fn() => "Transferência: " . ($statement->counterparty_name ?: $statement->description))
                            ->required(),
                    ])->statePath('transfer_new')
                ]),

            // SEÇÃO 3: POTENCIALIZADOR DE AUTOMAÇÃO EM LOTE E MEMÓRIA
            Section::make("Automação em Lote & Aprendizado")
                ->description("Resolva o histórico e ensine o robô para as próximas importações de extrato.")
                ->schema([
                    Toggle::make('resolve_similar_entries')
                        ->label("Conciliar também as {$similarCount} ocorrências semelhantes pendentes")
                        ->helperText("Aplica o mesmo favorecido, categoria e subcategoria em lote para todo o histórico pendente desta contraparte.")
                        ->default(true)
                        ->visible($similarCount > 0),

                    Toggle::make('remember_future_rule')
                        ->label("Memorizar regra contínua para '{$cleanSearchTerm}'")
                        ->helperText("Transações futuras que contenham este termo serão reconhecidas e pré-conciliadas automaticamente.")
                        ->default(false),
                ]),
        ]);
    }

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
        $category = null;
        $subcategory = null;

        if (in_array($type, [ConciliationType::TRANSFER->value, 'transferencia'])) {
            $tData = $data['transfer_new'] ?? [];
            if (!empty($tData['target_account_id'])) {
                $conciliable = BankAccount::find($tData['target_account_id']);
                $description = $tData['description'] ?? "Transferência";
            }
        } elseif (in_array($type, [ConciliationType::EXPENSE->value, 'avulsa'])) {
            if ($mode === 'link' && !empty($data['existing_payable_id'])) {
                $idRaw = $data["existing_payable_id"];
                if (str_starts_with($idRaw, "batch_")) {
                    $conciliable = PaymentBatch::find(str_replace("batch_", "", $idRaw));
                } else {
                    $conciliable = AccountPayable::find(str_replace("payable_", "", $idRaw));
                    $supplierId = $conciliable->supplier_id;
                    $category = $conciliable->accounting_category;
                    $subcategory = $conciliable->accounting_subcategory;
                }
            } elseif ($mode === 'new' && !empty($data['avulsa_new']['amount'])) {
                $cleanData = Arr::except($data['avulsa_new'], ['is_supplier_locked', 'is_status_locked']);
                $conciliable = AccountPayable::create($cleanData);
                $supplierId = $conciliable->supplier_id;
                $category = $conciliable->accounting_category;
                $subcategory = $conciliable->accounting_subcategory;
                $description = $conciliable->description;
            }
        } elseif (in_array($type, [ConciliationType::RECURRING->value, 'fixa'])) {
            if ($mode === 'link' && !empty($data['existing_fixed_id'])) {
                $fixed = RecurringExpense::find($data["existing_fixed_id"]);
                $desc = $fixed->name . " (" . $statement->bank_date->format("M/Y") . ")";
                $category = $fixed->accounting_category;
                $subcategory = $fixed->accounting_subcategory;
                $conciliable = AccountPayable::create([
                    "description" => $desc,
                    "amount" => abs((float) $statement->amount),
                    "supplier_id" => $fixed->supplier_id,
                    "accounting_category" => $category,
                    "accounting_subcategory" => $subcategory,
                    "due_date" => $statement->bank_date
                ]);
                $supplierId = $fixed->supplier_id;
            } elseif ($mode === 'new' && !empty($data['fixa_new']['name'])) {
                $fixed = RecurringExpense::create(Arr::except($data['fixa_new'], ['is_supplier_locked', 'is_status_locked']));
                $category = $fixed->accounting_category;
                $subcategory = $fixed->accounting_subcategory;
                $conciliable = AccountPayable::create([
                    "description" => $fixed->name . " (Inicial)",
                    "amount" => abs((float) $statement->amount),
                    "supplier_id" => $fixed->supplier_id,
                    "accounting_category" => $category,
                    "accounting_subcategory" => $subcategory,
                    "due_date" => $statement->bank_date
                ]);
                $supplierId = $fixed->supplier_id;
            }
        } elseif (in_array($type, [ConciliationType::PERSONAL->value, 'pessoal'])) {
            $category = AccountingCategory::PARTNERS->value;
            $subcategory = 'personal';
            if ($mode === 'link' && !empty($data['existing_personal_id'])) {
                $conciliable = AccountPayable::find($data['existing_personal_id']);
                $supplierId = $conciliable->supplier_id;
            } elseif ($mode === 'new') {
                $pData = $data['pessoal_new'] ?? [];
                $supplierId = !empty($pData['is_supplier_locked']) || (!empty($pData['identify_supplier']) && !empty($pData['supplier_id']))
                    ? $pData['supplier_id']
                    : Supplier::firstOrCreate(
                        ['name' => 'Uso Pessoal / Sócios'],
                        ['type' => 'physical', 'is_personal' => true, 'accounting_category' => AccountingCategory::PARTNERS->value, 'accounting_subcategory' => 'personal', 'is_active' => true]
                    )->id;
                $desc = !empty($pData['description'])
                    ? $pData['description'] . " - " . ($statement->counterparty_name ?: $statement->description)
                    : "Uso Pessoal: " . ($statement->counterparty_name ?: $statement->description);
                $conciliable = AccountPayable::create([
                    "description" => $desc,
                    "supplier_id" => $supplierId,
                    "amount" => abs((float) $statement->amount),
                    "due_date" => $statement->bank_date,
                    "is_personal" => true,
                    "accounting_category" => $category,
                    "accounting_subcategory" => $subcategory
                ]);
                $description = $desc;
            }
        }

        // 1. Finaliza a transação ativa no motor de conciliação
        if ($conciliable) {
            BankReconciliationService::finalize($statement, $conciliable, $supplierId, $description);
        }

        $searchTerm = $data['search_term_used'] ?? trim((string) ($statement->counterparty_name ?: $statement->description));

        // 2. Memoriza Regra Futura (se selecionado)
        if (!empty($data['remember_future_rule']) && !empty($searchTerm)) {
            $targetTypeEnum = match ($type) {
                'transferencia', ConciliationType::TRANSFER->value => ConciliationType::TRANSFER,
                'fixa', ConciliationType::RECURRING->value          => ConciliationType::RECURRING,
                'pessoal', ConciliationType::PERSONAL->value        => ConciliationType::PERSONAL,
                default                                            => ConciliationType::EXPENSE,
            };

            $targetAccId = ($targetTypeEnum === ConciliationType::TRANSFER && $conciliable instanceof BankAccount)
                ? $conciliable->id
                : null;

            BankReconciliationRule::updateOrCreate(
                ['search_term' => $searchTerm],
                [
                    'target_type'            => $targetTypeEnum,
                    'target_account_id'      => $targetAccId,
                    'supplier_id'            => $supplierId,
                    'accounting_category'    => $category,
                    'accounting_subcategory' => $subcategory,
                    'auto_reconcile'         => true,
                ]
            );
        }

        // 3. Resolve Ocorrências Semelhantes em Cascata (Batch Resolve)
        $shouldResolveSimilar = !empty($data['resolve_similar_entries']) || !empty($data['batch_resolve']) || !empty($data['conciliar_semelhantes']);

        if ($shouldResolveSimilar) {
            $doc = preg_replace('/[^0-9]/', '', (string) ($statement->document ?? ''));
            $counterName = trim((string) ($statement->counterparty_name ?? ''));
            $desc = trim((string) ($statement->description ?? ''));
            $sign = $statement->amount < 0 ? '<' : '>';

            $similarPending = BankStatement::query()
                ->where('status', 'pending')
                ->where('id', '!=', $statement->id)
                ->where('amount', $sign, 0)
                ->where(function ($q) use ($doc, $counterName, $desc, $searchTerm) {
                    if (!empty($doc) && strlen($doc) >= 11) {
                        $q->where('document', $doc);
                    }
                    if (!empty($counterName) && strlen($counterName) >= 4) {
                        $q->orWhere('counterparty_name', 'like', "%{$counterName}%");
                    }
                    if (!empty($searchTerm) && strlen($searchTerm) >= 4) {
                        $q->orWhere('counterparty_name', 'like', "%{$searchTerm}%")
                          ->orWhere('description', 'like', "%{$searchTerm}%");
                    }
                })
                ->get();

            foreach ($similarPending as $simStmt) {
                $simDesc = $description ?: ($simStmt->counterparty_name ?: $simStmt->description);

                // CENÁRIO A: Pessoal / Sócios
                if (in_array($type, [ConciliationType::PERSONAL->value, 'pessoal'])) {
                    $simPayable = AccountPayable::create([
                        "description"            => "Uso Pessoal: " . ($simStmt->counterparty_name ?: $simStmt->description),
                        "supplier_id"            => $supplierId,
                        "amount"                 => abs((float) $simStmt->amount),
                        "due_date"               => $simStmt->bank_date,
                        "is_personal"            => true,
                        "accounting_category"    => AccountingCategory::PARTNERS->value,
                        "accounting_subcategory" => "personal",
                    ]);
                    BankReconciliationService::finalize($simStmt, $simPayable, $supplierId, $simPayable->description);
                }
                // CENÁRIO B: Conta Fixa / Recorrente (Ex: Unifique, Aluguel)
                elseif (in_array($type, [ConciliationType::RECURRING->value, 'fixa'])) {
                    $fixedDesc = $simDesc . " (" . $simStmt->bank_date->format("M/Y") . ")";
                    $simPayable = AccountPayable::create([
                        "description"            => $fixedDesc,
                        "amount"                 => abs((float) $simStmt->amount),
                        "supplier_id"            => $supplierId,
                        "accounting_category"    => $category,
                        "accounting_subcategory" => $subcategory,
                        "due_date"               => $simStmt->bank_date,
                    ]);
                    BankReconciliationService::finalize($simStmt, $simPayable, $supplierId, $fixedDesc);
                }
                // CENÁRIO C: Despesa Avulsa
                elseif (in_array($type, [ConciliationType::EXPENSE->value, 'avulsa'])) {
                    $simPayable = AccountPayable::create([
                        "description"            => $simDesc,
                        "supplier_id"            => $supplierId,
                        "amount"                 => abs((float) $simStmt->amount),
                        "due_date"               => $simStmt->bank_date,
                        "accounting_category"    => $category,
                        "accounting_subcategory" => $subcategory,
                    ]);
                    BankReconciliationService::finalize($simStmt, $simPayable, $supplierId, $simDesc);
                }
                // CENÁRIO D: Transferência / Pagamento de Fatura de Cartão
                elseif (in_array($type, [ConciliationType::TRANSFER->value, 'transferencia']) && $conciliable instanceof BankAccount) {
                    $transfDesc = $description ?: ($simStmt->counterparty_name ?: "Transferência / Pgto de Fatura");
                    BankReconciliationService::finalize($simStmt, $conciliable, null, $transfDesc);
                }
            }
        }
    }
}
