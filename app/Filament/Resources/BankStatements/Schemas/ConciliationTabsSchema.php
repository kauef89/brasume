<?php

namespace App\Filament\Resources\BankStatements\Schemas;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\BankAccountType;
use App\Enums\BankMovementType;
use App\Enums\ConciliationType;
use App\Enums\LaborPaymentType;
use App\Filament\Resources\AccountPayables\Schemas\AccountPayableForm;
use App\Filament\Resources\RecurringExpenses\Schemas\RecurringExpenseForm;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\BankReconciliationRule;
use App\Models\BankStatement;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\PaymentBatch;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\RecurringExpense;
use App\Models\Supplier;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\BankReconciliationService;
use App\Services\Accounting\CardSettlementReconciliationService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ConciliationTabsSchema
{
    protected static function normalizeType(mixed $raw): string
    {
        return $raw instanceof \BackedEnum ? (string) $raw->value : (string) $raw;
    }

    /**
     * Calcula valores iniciais inteligentes com base no tipo de movimento OFX, CPF/CNPJ e score do motor.
     */
    public static function detectSmartDefaults(BankStatement $statement): array
    {
        $isCredit = (float) $statement->amount >= 0;
        $movType = $statement->movement_type instanceof BankMovementType
            ? $statement->movement_type->value
            : (string) ($statement->movement_type ?? '');

        $cleanDoc = preg_replace('/[^0-9]/', '', (string) $statement->document);
        $amount = abs((float) $statement->amount);
        $date = $statement->bank_date ? $statement->bank_date->format('Y-m-d') : now()->format('Y-m-d');
        $desc = $statement->counterparty_name ?: $statement->description;

        $supplierId = null;
        if (!empty($cleanDoc)) {
            $supplier = Supplier::where('document', $statement->document)
                ->orWhere('document', $cleanDoc)
                ->first();

            if (!$supplier) {
                $supplier = Supplier::whereNotNull('document')->get()->first(
                    fn ($s) => preg_replace('/[^0-9]/', '', (string) $s->document) === $cleanDoc
                );
            }
            $supplierId = $supplier?->id;
        }

        $employee = null;
        if (!$supplierId && !empty($cleanDoc)) {
            $employee = Employee::where('cpf', $cleanDoc)->first();
            if (!$employee) {
                $employee = Employee::whereNotNull('cpf')->get()->first(
                    fn ($e) => preg_replace('/[^0-9]/', '', (string) $e->cpf) === $cleanDoc
                );
            }
        }

        // Busca par oposto pendente (ex.: pagamento -R$ 95,00 + estorno +R$ 95,00 do mesmo CNPJ/CPF)
        $oppositeSign = $isCredit ? '<' : '>';
        $oppositeCandidate = null;

        if ($statement->bank_date) {
            $oppQuery = BankStatement::where('status', 'pending')
                ->where('id', '!=', $statement->id)
                ->where('bank_account_id', $statement->bank_account_id)
                ->where('amount', $oppositeSign, 0)
                ->whereBetween('bank_date', [
                    $statement->bank_date->copy()->subDays(45)->toDateString(),
                    $statement->bank_date->copy()->addDays(45)->toDateString(),
                ])
                ->whereRaw('ABS(ABS(amount) - ?) < 0.05', [$amount]);

            if (!empty($cleanDoc)) {
                $oppositeCandidate = (clone $oppQuery)->where('document', $statement->document)->first()
                    ?? (clone $oppQuery)->where('document', $cleanDoc)->first();
            }

            if (!$oppositeCandidate && $movType === BankMovementType::REFUND->value) {
                $oppositeCandidate = $oppQuery->first();
            }
        }

        $defaultType = null;
        $defaultActionMode = null;
        $defaultPayableId = null;
        $defaultFixedId = null;
        $defaultTransferTargetId = null;
        $defaultPayrollMode = 'avulso';
        $defaultPayrollBatchId = null;
        $defaultPayslipId = null;
        $defaultCategory = null;
        $defaultSubcategory = null;

        if ($oppositeCandidate !== null || $movType === BankMovementType::REFUND->value) {
            $defaultType = ConciliationType::REFUND->value;
        } elseif ($isCredit) {
            if ($movType === BankMovementType::CARD_IN->value || str_contains(mb_strtolower((string) $desc), 'repasse')) {
                $defaultType = ConciliationType::CARD_SETTLEMENT->value;
            } elseif ($movType === BankMovementType::ATM_IN->value) {
                $defaultType = ConciliationType::TRANSFER->value;
                $vault = BankAccount::where('is_active', true)
                    ->where('type', BankAccountType::VAULT->value)
                    ->where('id', '!=', $statement->bank_account_id)
                    ->first();
                $defaultTransferTargetId = $vault?->id;
            } elseif ($movType === BankMovementType::LOAN_IN->value) {
                $defaultType = ConciliationType::CAPITAL_IN->value;
            } elseif ($movType === BankMovementType::PIX_IN->value) {
                $defaultType = ConciliationType::RECEIVABLE->value;
            }
        } else {
            if ($movType === BankMovementType::PAYROLL->value || $employee !== null) {
                $defaultType = ConciliationType::PAYROLL->value;

                // Tenta localizar lote CNAB / Folha ou holerite pendente com valor exato
                $matchingBatch = PaymentBatch::whereIn('status', ['transmitted', 'pending', 'generated'])
                    ->whereRaw('ABS(total_amount - ?) < 0.05', [$amount])
                    ->latest('id')
                    ->first();

                if ($matchingBatch) {
                    $defaultPayrollMode = 'batch';
                    $defaultPayrollBatchId = 'batch_' . $matchingBatch->id;
                } elseif ($employee) {
                    $matchingPayslip = Payslip::where('employee_id', $employee->id)
                        ->where('status', 'pending')
                        ->whereRaw('ABS(net_salary - ?) < 0.05', [$amount])
                        ->latest('id')
                        ->first();

                    if ($matchingPayslip) {
                        $defaultPayrollMode = 'payslip';
                        $defaultPayslipId = $matchingPayslip->id;
                    } else {
                        $defaultPayrollMode = 'avulso';
                    }
                } else {
                    $defaultPayrollMode = 'batch';
                }
            } elseif ($movType === BankMovementType::CREDIT_CARD_PAYMENT->value) {
                $defaultType = ConciliationType::TRANSFER->value;
                $cardAccount = BankAccount::where('is_active', true)
                    ->where('type', BankAccountType::CREDIT_CARD->value)
                    ->where('id', '!=', $statement->bank_account_id)
                    ->first();
                $defaultTransferTargetId = $cardAccount?->id;
            } elseif ($movType === BankMovementType::ATM_OUT->value) {
                $defaultType = ConciliationType::TRANSFER->value;
                $vault = BankAccount::where('is_active', true)
                    ->where('type', BankAccountType::VAULT->value)
                    ->where('id', '!=', $statement->bank_account_id)
                    ->first();
                $defaultTransferTargetId = $vault?->id;
            } elseif ($movType === BankMovementType::FIXED_BILL->value) {
                $defaultType = ConciliationType::RECURRING->value;
                $defaultActionMode = 'link';
            } elseif ($movType === BankMovementType::TAX_BILL->value) {
                $defaultType = ConciliationType::EXPENSE->value;
                $defaultActionMode = 'new';
                $defaultCategory = AccountingCategory::ADMINISTRATIVE->value;
                $defaultSubcategory = AccountingSubcategory::TAXES->value;
            } elseif ($movType === BankMovementType::BANK_FEE->value) {
                $defaultType = ConciliationType::EXPENSE->value;
                $defaultActionMode = 'new';
                $defaultCategory = AccountingCategory::ADMINISTRATIVE->value;
                $defaultSubcategory = AccountingSubcategory::BANK_FEES->value;
            } elseif (!empty($statement->suggested_payable_id)) {
                $defaultType = ConciliationType::EXPENSE->value;
                $defaultActionMode = 'link';
                $defaultPayableId = 'payable_' . $statement->suggested_payable_id;
            } elseif ($supplierId) {
                $sup = Supplier::find($supplierId);
                $defaultCategory = $sup?->accounting_category;
                $defaultSubcategory = $sup?->accounting_subcategory;
            }
        }

        return [
            'conciliation_type'      => $defaultType,
            'refund_mode'            => $oppositeCandidate ? 'pair_statement' : ($isCredit ? 'contra_payable' : 'contra_receivable'),
            'refund_statement_id'    => $oppositeCandidate?->id,
            'refund_payable_id'      => null,
            'refund_receivable_id'   => null,
            'refund_note'            => '',
            'action_mode'            => $defaultActionMode,
            'existing_payable_id'    => $defaultPayableId,
            'existing_fixed_id'      => $defaultFixedId,
            'existing_personal_id'   => null,
            'existing_receivable_id' => null,
            'payroll_mode'           => $defaultPayrollMode,
            'payroll_batch_id'       => $defaultPayrollBatchId,
            'payslip_id'             => $defaultPayslipId,
            'payroll_new'            => [
                'employee_id'            => $employee?->id,
                'labor_type'             => LaborPaymentType::ADVANCE->value,
                'accounting_subcategory' => AccountingSubcategory::PAYROLL->value,
                'description'            => '',
            ],
            'transfer_new'           => [
                'target_account_id' => $defaultTransferTargetId,
                'description'       => ($movType === BankMovementType::CREDIT_CARD_PAYMENT->value ? 'Pagamento Fatura: ' : 'Transferência: ') . $desc,
            ],
            'capital_new'            => [
                'kind'        => $movType === BankMovementType::LOAN_IN->value ? 'loan' : 'other_in',
                'description' => $desc,
            ],
            'avulsa_new'             => [
                'amount'                 => $amount,
                'due_date'               => $date,
                'payment_date'           => $date,
                'status'                 => 'paid',
                'is_status_locked'       => true,
                'description'            => $desc,
                'supplier_id'            => $supplierId,
                'is_supplier_locked'     => $supplierId !== null,
                'accounting_category'    => $defaultCategory,
                'accounting_subcategory' => $defaultSubcategory,
            ],
            'fixa_new'               => [
                'default_amount'     => $amount,
                'due_day'            => $statement->bank_date ? $statement->bank_date->format('d') : '1',
                'name'               => $desc,
                'supplier_id'        => $supplierId,
                'is_supplier_locked' => $supplierId !== null,
            ],
            'pessoal_new'            => [
                'description'        => '',
                'supplier_id'        => $supplierId,
                'is_supplier_locked' => $supplierId !== null,
            ],
        ];
    }

    public static function make(BankStatement $statement): Group
    {
        $isCredit = (float) $statement->amount >= 0;
        $amountStr = number_format(abs((float) $statement->amount), 2, ',', '.');
        $colorClass = $isCredit ? 'text-success-600' : 'text-danger-600';
        $dateStr = $statement->bank_date ? $statement->bank_date->format('d/m/Y') : '-';
        $descStr = $statement->counterparty_name ?: $statement->description;
        $docStr = $statement->document
            ? "<span class='ml-2 px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'>DOC: {$statement->document}</span>"
            : '';

        // Deteção Canónica de Semelhantes
        $cleanSearchTerm = self::extractCanonicalTerm($statement);
        $sign = $isCredit ? '>' : '<';

        $similarQuery = BankStatement::where('status', 'pending')
            ->where('id', '!=', $statement->id)
            ->where('amount', $sign, 0)
            ->where(function ($q) use ($cleanSearchTerm) {
                if (strlen($cleanSearchTerm) >= 3) {
                    $q->where('counterparty_name', 'like', "%{$cleanSearchTerm}%")
                      ->orWhere('description', 'like', "%{$cleanSearchTerm}%");
                }
            });

        $similarCount = strlen($cleanSearchTerm) >= 3 ? $similarQuery->count() : 0;
        $similarSum = $similarCount > 0 ? abs((float) $similarQuery->sum('amount')) : 0.0;

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

        $typesWithActionMode = [
            ConciliationType::EXPENSE->value,
            ConciliationType::RECURRING->value,
            ConciliationType::PERSONAL->value,
        ];

        $typesWithBatchLearning = [
            ConciliationType::EXPENSE->value,
            ConciliationType::RECURRING->value,
            ConciliationType::PERSONAL->value,
            ConciliationType::TRANSFER->value,
        ];

        return Group::make([
            Placeholder::make('statement_header')->hiddenLabel()->content($headerHtml),
            \Filament\Forms\Components\Hidden::make('is_supplier_locked')->default(false)->dehydrated(false),
            \Filament\Forms\Components\Hidden::make('search_term_used')->default($cleanSearchTerm),

            ToggleButtons::make('conciliation_type')
                ->label('1. Qual é a natureza desta transação?')
                ->options(ConciliationType::optionsForDirection($isCredit))
                ->icons(
                    collect(ConciliationType::optionsForDirection($isCredit))
                        ->mapWithKeys(fn ($label, $val) => [$val => ConciliationType::from($val)->getIcon()])
                        ->all()
                )
                ->colors(
                    collect(ConciliationType::optionsForDirection($isCredit))
                        ->mapWithKeys(fn ($label, $val) => [$val => ConciliationType::from($val)->getColor()])
                        ->all()
                )
                ->inline()
                ->live()
                ->required(),

            ToggleButtons::make('action_mode')
                ->label('2. O que deseja fazer?')
                ->options(['link' => 'Vincular a um registo pendente', 'new' => 'Lançar como novo registo'])
                ->icons(['link' => 'heroicon-o-link', 'new' => 'heroicon-o-plus-circle'])
                ->inline()
                ->live()
                ->required(fn (Get $get) => in_array(self::normalizeType($get('conciliation_type')), $typesWithActionMode, true))
                ->visible(fn (Get $get) => in_array(self::normalizeType($get('conciliation_type')), $typesWithActionMode, true)),

            // =========================================================================
            // FLUXO 1: DESPESA OPERACIONAL
            // =========================================================================
            Section::make("Vincular a Despesa / Lote / Romaneio Pendente")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::EXPENSE->value && $get('action_mode') === 'link')
                ->schema([
                    Select::make("existing_payable_id")
                        ->hiddenLabel()
                        ->searchable()
                        ->placeholder("Busque por fornecedor, canhoto de romaneio ou valor...")
                        ->options(function () {
                            $payables = AccountPayable::where("status", "pending")
                                ->whereNull("payment_batch_id")
                                ->where("is_personal", false)
                                ->orderByDesc('due_date')
                                ->get()
                                ->mapWithKeys(fn ($p) => [
                                    "payable_" . $p->id => "📄 " . ($p->supplier->name ?? ($p->employee->name ?? "S/F"))
                                        . " - R$ " . number_format((float) $p->amount, 2, ",", ".")
                                        . " (" . $p->description . " • Venc: " . ($p->due_date?->format('d/m') ?? '-') . ")"
                                ]);
                            $batches = PaymentBatch::whereIn("status", ["pending", "transmitted", "generated"])
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn ($b) => [
                                    "batch_" . $b->id => "📦 LOTE #{$b->id}: " . $b->description . " - R$ " . number_format((float) $b->total_amount, 2, ",", ".")
                                ]);
                            return collect($payables)->union($batches)->toArray();
                        }),
                ]),

            Section::make("Lançar Nova Despesa Operacional")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::EXPENSE->value && $get('action_mode') === 'new')
                ->schema([
                    Group::make(AccountPayableForm::getFormSchema(true))->statePath('avulsa_new')
                ]),

            // =========================================================================
            // FLUXO 2: CONTA FIXA (RECORRENTE)
            // =========================================================================
            Section::make("Vincular a uma Conta Recorrente")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::RECURRING->value && $get('action_mode') === 'link')
                ->schema([
                    Select::make("existing_fixed_id")
                        ->hiddenLabel()
                        ->searchable()
                        ->placeholder("Selecione o Contrato / Assinatura")
                        ->options(fn () => RecurringExpense::where("is_active", 1)->get()->mapWithKeys(
                            fn ($f) => [$f->id => "🔄 " . ($f->supplier->name ?? "S/F") . " - " . $f->name]
                        )),
                ]),

            Section::make("Cadastrar Nova Conta Recorrente")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::RECURRING->value && $get('action_mode') === 'new')
                ->schema([
                    Group::make(RecurringExpenseForm::getFormSchema(true))->statePath('fixa_new')
                ]),

            // =========================================================================
            // FLUXO 3: FOLHA & EQUIPE (CNAB 240 / HOLERITE / VALE / DIÁRIA / MOTOBOY)
            // =========================================================================
            Section::make("Folha de Pagamento, Holerite ou Vale / Diária")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::PAYROLL->value)
                ->schema([
                    ToggleButtons::make('payroll_mode')
                        ->label('Tipo de Pagamento à Equipe')
                        ->options([
                            'batch'   => 'Lote CNAB 240 / Folha',
                            'payslip' => 'Holerite Individual',
                            'avulso'  => 'Vale / Diária / Motoboy',
                        ])
                        ->icons([
                            'batch'   => 'heroicon-o-rectangle-stack',
                            'payslip' => 'heroicon-o-document-check',
                            'avulso'  => 'heroicon-o-user-plus',
                        ])
                        ->inline()
                        ->live()
                        ->default('avulso'),

                    Select::make('payroll_batch_id')
                        ->label('Lote Bancário CNAB 240 ou Folha Mensal')
                        ->visible(fn (Get $get) => $get('payroll_mode') === 'batch')
                        ->searchable()
                        ->placeholder('Selecione o lote transmitido ou a folha mensal...')
                        ->options(function () {
                            $batches = PaymentBatch::whereIn('status', ['transmitted', 'pending', 'generated'])
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn ($b) => [
                                    'batch_' . $b->id => "📦 Lote #{$b->id}: {$b->description} — R$ " . number_format((float) $b->total_amount, 2, ',', '.')
                                ]);

                            $payrolls = Payroll::whereIn('status', ['draft', 'approved'])
                                ->orderByDesc('reference_period')
                                ->get()
                                ->mapWithKeys(function ($p) {
                                    $comp = Carbon::parse($p->reference_period)->format('m/Y');
                                    return [
                                        'payroll_' . $p->id => "📑 Folha Competência {$comp} — Líquido: R$ " . number_format((float) $p->total_net, 2, ',', '.')
                                    ];
                                });

                            return collect($batches)->union($payrolls)->toArray();
                        }),

                    Select::make('payslip_id')
                        ->label('Holerite Pendente do Colaborador')
                        ->visible(fn (Get $get) => $get('payroll_mode') === 'payslip')
                        ->searchable()
                        ->placeholder('Selecione o holerite...')
                        ->options(function () {
                            return Payslip::with(['employee', 'payroll'])
                                ->where('status', 'pending')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(function ($ps) {
                                    $empName = $ps->employee?->name ?? "Colaborador #{$ps->employee_id}";
                                    $comp = $ps->payroll?->reference_period
                                        ? Carbon::parse($ps->payroll->reference_period)->format('m/Y')
                                        : '-';
                                    return [
                                        $ps->id => "👤 {$empName} (Comp. {$comp}) — Líquido: R$ " . number_format((float) $ps->net_salary, 2, ',', '.')
                                    ];
                                });
                        }),

                    Group::make([
                        Grid::make(2)->schema([
                            Select::make('employee_id')
                                ->label('Colaborador')
                                ->options(fn () => Employee::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::PAYROLL->value && $get('payroll_mode') === 'avulso'),

                            Select::make('labor_type')
                                ->label('Natureza Trabalhista')
                                ->options(LaborPaymentType::class)
                                ->default(LaborPaymentType::ADVANCE->value)
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    $val = self::normalizeType($state);
                                    $sub = match ($val) {
                                        LaborPaymentType::FREELANCE_DAILY->value,
                                        LaborPaymentType::DELIVERY_FEE->value,
                                        LaborPaymentType::EXTRA_SERVICE->value => AccountingSubcategory::FREELANCERS->value,
                                        default => AccountingSubcategory::PAYROLL->value,
                                    };
                                    $set('accounting_subcategory', $sub);
                                })
                                ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::PAYROLL->value && $get('payroll_mode') === 'avulso'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('accounting_subcategory')
                                ->label('Linha na DRE (03. Pessoal & Equipe)')
                                ->options(AccountingSubcategory::forCategory(AccountingCategory::PAYROLL))
                                ->default(AccountingSubcategory::PAYROLL->value)
                                ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::PAYROLL->value && $get('payroll_mode') === 'avulso'),

                            TextInput::make('description')
                                ->label('Observação / Referência')
                                ->placeholder('Ex: Vale quinzenal, Diária sábado, Corridas...'),
                        ]),
                    ])
                        ->statePath('payroll_new')
                        ->visible(fn (Get $get) => $get('payroll_mode') === 'avulso'),
                ]),

            // =========================================================================
            // FLUXO 4: DESPESA PESSOAL / SÓCIO
            // =========================================================================
            Section::make("Vincular a Despesa Pessoal Pendente")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::PERSONAL->value && $get('action_mode') === 'link')
                ->schema([
                    Select::make("existing_personal_id")
                        ->hiddenLabel()
                        ->searchable()
                        ->placeholder("Busque pelas suas despesas pendentes...")
                        ->options(fn () => AccountPayable::where("status", "pending")->where("is_personal", true)->get()->mapWithKeys(
                            fn ($p) => [$p->id => "👤 " . ($p->supplier->name ?? "S/F") . " - R$ " . number_format((float) $p->amount, 2, ",", ".") . " ({$p->description})"]
                        )),
                ]),

            Section::make("Lançar Nova Despesa Pessoal")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::PERSONAL->value && $get('action_mode') === 'new')
                ->schema([
                    Group::make([
                        Toggle::make('identify_supplier')
                            ->label('Deseja identificar o favorecido/sócio?')
                            ->live()
                            ->default(false)
                            ->hidden(fn (Get $get) => $get('is_supplier_locked')),
                        Select::make('supplier_id')
                            ->label('Favorecido')
                            ->options(Supplier::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn (Get $get) => $get('is_supplier_locked') || $get('identify_supplier'))
                            ->required(fn (Get $get) => $get('is_supplier_locked') || $get('identify_supplier'))
                            ->disabled(fn (Get $get) => $get('is_supplier_locked'))
                            ->dehydrated()
                            ->createOptionForm([
                                Section::make("Novo Favorecido")->schema(\App\Filament\Resources\Suppliers\Schemas\SupplierForm::getFormSchema())
                            ])
                            ->createOptionAction(fn ($action) => $action->fillForm([
                                'document' => $statement->document,
                                'name'     => $statement->counterparty_name ?: $statement->description,
                            ]))
                            ->createOptionUsing(fn (array $data) => Supplier::create($data)->id),
                        TextInput::make('description')
                            ->label('Descrição do Gasto')
                            ->placeholder('Deixe vazio para manter a descrição do banco'),
                    ])->statePath('pessoal_new')
                ]),

            // =========================================================================
            // FLUXO 4B: ESTORNO / DEVOLUÇÃO (PAREAMENTO OU CONTRA-LANÇAMENTO DRE)
            // =========================================================================
            Section::make("Estorno / Devolução de Valor")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::REFUND->value)
                ->schema([
                    ToggleButtons::make('refund_mode')
                        ->label('Como este estorno deve ser tratado?')
                        ->options(
                            $isCredit
                                ? [
                                    'pair_statement' => 'Parear com Saída Pendente no Extrato',
                                    'contra_payable' => 'Abater de Despesa Já Conciliada (DRE)',
                                ]
                                : [
                                    'pair_statement'    => 'Parear com Entrada Pendente no Extrato',
                                    'contra_receivable' => 'Devolução para Cliente / Pedido',
                                ]
                        )
                        ->icons([
                            'pair_statement'    => 'heroicon-o-arrows-right-left',
                            'contra_payable'    => 'heroicon-o-receipt-refund',
                            'contra_receivable' => 'heroicon-o-user-minus',
                        ])
                        ->inline()
                        ->live()
                        ->default($isCredit ? 'pair_statement' : 'contra_receivable'),

                    Select::make('refund_statement_id')
                        ->label($isCredit ? 'Saída Original Pendente no Extrato' : 'Entrada Original Pendente no Extrato')
                        ->visible(fn (Get $get) => $get('refund_mode') === 'pair_statement')
                        ->searchable()
                        ->placeholder('Selecione o lançamento oposto no extrato...')
                        ->options(function () use ($statement, $isCredit) {
                            $oppSign = $isCredit ? '<' : '>';
                            return BankStatement::where('status', 'pending')
                                ->where('id', '!=', $statement->id)
                                ->where('bank_account_id', $statement->bank_account_id)
                                ->where('amount', $oppSign, 0)
                                ->orderByDesc('bank_date')
                                ->limit(120)
                                ->get()
                                ->mapWithKeys(function (BankStatement $s) {
                                    $dt = $s->bank_date?->format('d/m/Y') ?? '-';
                                    $val = number_format(abs((float) $s->amount), 2, ',', '.');
                                    $name = $s->counterparty_name ?: $s->description;
                                    $doc = $s->document ? " • DOC: {$s->document}" : '';
                                    return [$s->id => "↩️ {$dt} | R$ {$val} | {$name}{$doc}"];
                                });
                        })
                        ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::REFUND->value && $get('refund_mode') === 'pair_statement'),

                    Placeholder::make('refund_pair_info')
                        ->hiddenLabel()
                        ->visible(fn (Get $get) => $get('refund_mode') === 'pair_statement' && filled($get('refund_statement_id')))
                        ->content(new HtmlString("
                            <div class='p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-900 dark:text-emerald-200'>
                                ✔ Os dois lançamentos serão conciliados em par no Livro Caixa (preservando o saldo bancário de cada data) com <b>impacto líquido zero na DRE</b>.
                            </div>
                        ")),

                    Select::make('refund_payable_id')
                        ->label('Despesa Já Conciliada a Estornar (Abate na DRE)')
                        ->visible(fn (Get $get) => $isCredit && $get('refund_mode') === 'contra_payable')
                        ->searchable()
                        ->placeholder('Busque pelo fornecedor, descrição ou valor pago...')
                        ->options(function () {
                            return AccountPayable::with('supplier')
                                ->where('status', 'paid')
                                ->where('amount', '>', 0)
                                ->orderByDesc('payment_date')
                                ->limit(150)
                                ->get()
                                ->mapWithKeys(function (AccountPayable $p) {
                                    $dt = $p->payment_date?->format('d/m/Y') ?? ($p->due_date?->format('d/m/Y') ?? '-');
                                    $val = number_format((float) $p->amount, 2, ',', '.');
                                    $sup = $p->supplier?->name ?? 'Fornecedor';
                                    return [$p->id => "📄 {$dt} | {$sup} — R$ {$val} ({$p->description})"];
                                });
                        })
                        ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::REFUND->value && $isCredit && $get('refund_mode') === 'contra_payable'),

                    Select::make('refund_receivable_id')
                        ->label('Recebimento / Pedido do Cliente Estornado')
                        ->visible(fn (Get $get) => !$isCredit && $get('refund_mode') === 'contra_receivable')
                        ->searchable()
                        ->placeholder('Busque pelo pedido ou cliente...')
                        ->options(function () {
                            return AccountReceivable::with(['order', 'customer'])
                                ->whereIn('status', ['paid', 'pending'])
                                ->where('net_amount', '>', 0)
                                ->orderByDesc('due_date')
                                ->limit(150)
                                ->get()
                                ->mapWithKeys(function (AccountReceivable $ar) {
                                    $cust = $ar->customer?->name ?? $ar->order?->customer_name ?? 'Cliente';
                                    $ord = $ar->order_id ? "Pedido #{$ar->order_id} • " : '';
                                    $val = number_format((float) $ar->net_amount, 2, ',', '.');
                                    return [$ar->id => "🧾 {$ord}{$cust} — R$ {$val}"];
                                });
                        })
                        ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::REFUND->value && !$isCredit && $get('refund_mode') === 'contra_receivable'),

                    TextInput::make('refund_note')
                        ->label('Motivo do Estorno (Opcional)')
                        ->placeholder('Ex: Serviço não prestado'),
                ]),

            // =========================================================================
            // FLUXO 5: TRANSFERÊNCIA ENTRE CONTAS / PAGAMENTO DE FATURA DE CARTÃO
            // =========================================================================
            Section::make("Detalhes da Transferência / Fatura de Cartão")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::TRANSFER->value)
                ->schema([
                    Group::make([
                        Select::make('target_account_id')
                            ->label('Conta de Destino / Origem (Banco, Cofre ou Cartão)')
                            ->helperText('Para pagamento de fatura, selecione a conta do tipo Cartão de Crédito correspondente.')
                            ->options(function () use ($statement) {
                                return BankAccount::where('is_active', true)
                                    ->where('id', '!=', $statement->bank_account_id)
                                    ->get()
                                    ->mapWithKeys(function (BankAccount $acc) {
                                        $typeVal = $acc->type instanceof \BackedEnum ? $acc->type->value : (string) $acc->type;
                                        $prefix = match ($typeVal) {
                                            BankAccountType::CREDIT_CARD->value => '💳 Cartão: ',
                                            BankAccountType::VAULT->value       => '🔐 Cofre: ',
                                            default                             => '🏦 Conta: ',
                                        };
                                        $bal = number_format((float) $acc->current_balance, 2, ',', '.');
                                        return [$acc->id => "{$prefix}{$acc->name} (Saldo: R$ {$bal})"];
                                    });
                            })
                            ->searchable()
                            ->live()
                            ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::TRANSFER->value),

                        Placeholder::make('target_account_audit')
                            ->hiddenLabel()
                            ->visible(fn (Get $get) => filled($get('target_account_id')))
                            ->content(function (Get $get) use ($statement) {
                                $targetId = $get('target_account_id');
                                if (!$targetId) return '';
                                $acc = BankAccount::find($targetId);
                                if (!$acc) return '';

                                $typeVal = $acc->type instanceof \BackedEnum ? $acc->type->value : (string) $acc->type;
                                $bal = (float) $acc->current_balance;
                                $balFmt = number_format($bal, 2, ',', '.');
                                $stmtAmt = abs((float) $statement->amount);
                                $stmtFmt = number_format($stmtAmt, 2, ',', '.');

                                if ($typeVal === BankAccountType::CREDIT_CARD->value) {
                                    $openDebt = abs(min(0.0, $bal));
                                    $debtFmt = number_format($openDebt, 2, ',', '.');

                                    if ($openDebt <= 0.01) {
                                        return new HtmlString("
                                            <div class='p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-700 text-xs text-amber-900 dark:text-amber-200 space-y-1'>
                                                <div class='font-bold'>⚠️ Atenção: Fatura sem compras prévias lançadas no cartão!</div>
                                                <div>O saldo atual de <b>{$acc->name}</b> é <b>R$ {$balFmt}</b>. Se você conciliar este pagamento de <b>R$ {$stmtFmt}</b> como transferência sem ter lançado as compras individuais no cartão, essa despesa não aparecerá na DRE.</div>
                                            </div>
                                        ");
                                    }

                                    return new HtmlString("
                                        <div class='p-3 rounded-xl bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 text-xs text-blue-900 dark:text-blue-200'>
                                            💳 <b>{$acc->name}</b> possui <b>R$ {$debtFmt}</b> em compras registradas. Este pagamento abaterá R$ {$stmtFmt} do saldo devedor sem duplicar na DRE.
                                        </div>
                                    ");
                                }

                                return new HtmlString("
                                    <div class='p-2.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs text-gray-700 dark:text-gray-300'>
                                        Saldo atual em <b>{$acc->name}</b>: <b>R$ {$balFmt}</b>
                                    </div>
                                ");
                            }),

                        TextInput::make('description')
                            ->label('Descrição da Movimentação')
                            ->default(fn () => "Transferência: " . ($statement->counterparty_name ?: $statement->description))
                            ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::TRANSFER->value),
                    ])->statePath('transfer_new')
                ]),

            // =========================================================================
            // FLUXO 6: REPASSE DE CARTÃO (ADQUIRENTE - ENTRADAS)
            // =========================================================================
            Section::make("Liquidação de Lote de Cartão (Adquirente)")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::CARD_SETTLEMENT->value)
                ->schema([
                    Placeholder::make('card_batch_preview')
                        ->hiddenLabel()
                        ->content(function () use ($statement) {
                            $service = app(CardSettlementReconciliationService::class);
                            $parsed = $service->parseBankCardSettlement($statement);
                            if (!$parsed || empty($parsed['brand'])) {
                                return new HtmlString("<div class='text-sm text-amber-600'>Não foi possível identificar automaticamente a bandeira neste lançamento.</div>");
                            }

                            $txs = $service->findCandidateTransactions($statement, true);
                            if ($txs->isEmpty()) {
                                return new HtmlString("<div class='text-sm text-gray-500'>Nenhuma venda pendente localizada para <b>{$parsed['brand']}</b> nesta janela.</div>");
                            }

                            if (!$parsed['is_anticipation']) {
                                $byDate = $txs->groupBy(fn ($tx) => $tx->expected_settlement_date->toDateString());
                                $targetDate = $byDate->has($statement->bank_date->toDateString())
                                    ? $statement->bank_date->toDateString()
                                    : $byDate->keys()->first();
                                $txs = $byDate->get($targetDate);
                            }

                            $netSum = $service->sumTransactionsNet($txs);
                            $bankAmt = round(abs((float) $statement->amount), 2);
                            $diff = round($bankAmt - $netSum, 2);
                            $diffFmt = ($diff >= 0 ? '+' : '-') . 'R$ ' . number_format(abs($diff), 2, ',', '.');
                            $netFmt = number_format($netSum, 2, ',', '.');
                            $bankFmt = number_format($bankAmt, 2, ',', '.');

                            return new HtmlString("
                                <div class='p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-xs space-y-1.5'>
                                    <div class='font-bold text-emerald-900 dark:text-emerald-200 text-sm'>💳 Lote Localizado: {$parsed['brand']} ({$txs->count()} vendas)</div>
                                    <div>Soma líquida na adquirente: <b>R$ {$netFmt}</b> &nbsp;•&nbsp; Depositado no banco: <b>R$ {$bankFmt}</b></div>
                                    <div>Diferença (corte de horário / RAV): <b>{$diffFmt}</b></div>
                                    <div class='text-[11px] opacity-80 pt-1'>Ao salvar, todas as {$txs->count()} vendas do lote serão baixadas como liquidadas na data do extrato.</div>
                                </div>
                            ");
                        }),
                ]),

            // =========================================================================
            // FLUXO 7: RECEBIMENTO DE CLIENTE / PEDIDO PDV / FIADO (ENTRADAS)
            // =========================================================================
            Section::make("Vincular Recebimento a Venda / Cliente")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::RECEIVABLE->value)
                ->schema([
                    Select::make('existing_receivable_id')
                        ->label('Conta a Receber ou Pedido Pendente')
                        ->searchable()
                        ->placeholder('Busque pelo número do pedido, cliente ou valor...')
                        ->options(function () {
                            $receivables = AccountReceivable::with(['order', 'customer'])
                                ->where('status', 'pending')
                                ->orderByDesc('due_date')
                                ->limit(150)
                                ->get()
                                ->mapWithKeys(function (AccountReceivable $ar) {
                                    $cust = $ar->customer?->name ?? $ar->order?->customer_name ?? 'Cliente';
                                    $orderTag = $ar->order_id ? "Pedido #{$ar->order_id} • " : '';
                                    $val = number_format((float) $ar->net_amount, 2, ',', '.');
                                    $due = $ar->due_date?->format('d/m') ?? '-';
                                    return [
                                        'ar_' . $ar->id => "💰 {$orderTag}{$cust} — R$ {$val} (Prev: {$due})"
                                    ];
                                });

                            $unpaidOrders = Order::whereNotIn('status', ['cancelled', 'canceled', 'rejected'])
                                ->where('payment_status', '!=', 'paid')
                                ->orderByDesc('id')
                                ->limit(100)
                                ->get()
                                ->mapWithKeys(function (Order $o) {
                                    $cust = $o->customer_name ?: 'Consumidor';
                                    $val = number_format((float) $o->total, 2, ',', '.');
                                    $dt = $o->created_at?->format('d/m H:i') ?? '-';
                                    return [
                                        'order_' . $o->id => "🧾 Pedido #{$o->id} ({$cust}) — R$ {$val} [{$dt}]"
                                    ];
                                });

                            return collect($receivables)->union($unpaidOrders)->toArray();
                        })
                        ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::RECEIVABLE->value),
                ]),

            // =========================================================================
            // FLUXO 8: EMPRÉSTIMO / APORTE / OUTRAS ENTRADAS NÃO-OPERACIONAIS
            // =========================================================================
            Section::make("Classificação de Entrada Não-Operacional")
                ->visible(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::CAPITAL_IN->value)
                ->schema([
                    Group::make([
                        Select::make('kind')
                            ->label('Origem do Recurso')
                            ->options([
                                'loan'       => '🏦 Liberação de Empréstimo / Capital de Giro',
                                'partner_in' => '👤 Aporte de Capital / Sócio',
                                'refund'     => '↩️ Estorno / Devolução de Fornecedor',
                                'other_in'   => '➕ Outra Entrada Extraordinária',
                            ])
                            ->default('loan')
                            ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::CAPITAL_IN->value),

                        TextInput::make('description')
                            ->label('Descrição')
                            ->required(fn (Get $get) => self::normalizeType($get('conciliation_type')) === ConciliationType::CAPITAL_IN->value),
                    ])->statePath('capital_new')
                ]),

            // =========================================================================
            // AUTOMAÇÃO EM LOTE & MEMÓRIA CONTÍNUA
            // =========================================================================
            Section::make("Automação em Lote & Aprendizado")
                ->description("Resolva o histórico e ensine o robô para as próximas importações de extrato.")
                ->visible(fn (Get $get) => in_array(self::normalizeType($get('conciliation_type')), $typesWithBatchLearning, true))
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
        $type = self::normalizeType($data['conciliation_type'] ?? '');
        $mode = (string) ($data['action_mode'] ?? '');

        if (!$type && !empty($data['existing_payable_id'])) {
            $type = ConciliationType::EXPENSE->value;
            $mode = 'link';
        }

        // 0. Estorno / Devolução (Pareamento de Extrato ou Contra-Lançamento DRE)
        if ($type === ConciliationType::REFUND->value) {
            $rMode = (string) ($data['refund_mode'] ?? 'pair_statement');
            $rNote = trim((string) ($data['refund_note'] ?? ''));

            if ($rMode === 'pair_statement' && !empty($data['refund_statement_id'])) {
                $otherStmt = BankStatement::find((int) $data['refund_statement_id']);
                if ($otherStmt) {
                    BankReconciliationService::pairRefundStatements($statement, $otherStmt, $rNote !== '' ? $rNote : null);
                }
            } elseif ($rMode === 'contra_payable' && !empty($data['refund_payable_id'])) {
                $origPayable = AccountPayable::find((int) $data['refund_payable_id']);
                if ($origPayable) {
                    $refundDesc = "Estorno: {$origPayable->description}" . ($rNote !== '' ? " ({$rNote})" : '');
                    $contraPayable = AccountPayable::create([
                        'description'            => $refundDesc,
                        'supplier_id'            => $origPayable->supplier_id,
                        'employee_id'            => $origPayable->employee_id,
                        'amount'                 => -abs((float) $statement->amount),
                        'due_date'               => $statement->bank_date,
                        'status'                 => 'pending',
                        'is_personal'            => (bool) $origPayable->is_personal,
                        'accounting_category'    => $origPayable->accounting_category,
                        'accounting_subcategory' => $origPayable->accounting_subcategory,
                        'observations'           => "Contra-lançamento de estorno referente ao título #{$origPayable->id}.",
                    ]);
                    BankReconciliationService::finalize($statement, $contraPayable, $origPayable->supplier_id, $refundDesc);
                }
            } elseif ($rMode === 'contra_receivable' && !empty($data['refund_receivable_id'])) {
                $origAr = AccountReceivable::find((int) $data['refund_receivable_id']);
                if ($origAr) {
                    $refundDesc = "Devolução ao Cliente: {$origAr->description}" . ($rNote !== '' ? " ({$rNote})" : '');
                    $contraAr = AccountReceivable::create([
                        'order_id'        => $origAr->order_id,
                        'bank_account_id' => $statement->bank_account_id,
                        'customer_id'     => $origAr->customer_id,
                        'description'     => $refundDesc,
                        'payment_method'  => $origAr->payment_method,
                        'installment'     => '1/1',
                        'gross_amount'    => -abs((float) $statement->amount),
                        'fee_amount'      => 0,
                        'net_amount'      => -abs((float) $statement->amount),
                        'due_date'        => $statement->bank_date,
                        'status'          => 'pending',
                    ]);
                    BankReconciliationService::finalize($statement, $contraAr, null, $refundDesc);
                }
            }
            return;
        }

        // 1. Repasse de Cartão (Motor dedicado)
        if ($type === ConciliationType::CARD_SETTLEMENT->value) {
            $cardService = app(CardSettlementReconciliationService::class);
            $parsed = $cardService->parseBankCardSettlement($statement);
            if ($parsed && !empty($parsed['brand'])) {
                $txs = $cardService->findCandidateTransactions($statement, true);
                if (!$parsed['is_anticipation'] && $txs->isNotEmpty()) {
                    $byDate = $txs->groupBy(fn ($tx) => $tx->expected_settlement_date->toDateString());
                    $targetDate = $byDate->has($statement->bank_date->toDateString())
                        ? $statement->bank_date->toDateString()
                        : $byDate->keys()->first();
                    $txs = $byDate->get($targetDate);
                }

                if ($txs->isNotEmpty()) {
                    $bankAmt = round(abs((float) $statement->amount), 2);
                    $netSum = $cardService->sumTransactionsNet($txs);
                    $ravDiff = $parsed['is_anticipation'] ? max(0.0, round($netSum - $bankAmt, 2)) : 0.0;
                    $cardService->createSettlementBatch(
                        $statement,
                        $txs,
                        $bankAmt,
                        $parsed['brand'],
                        $parsed['modality'],
                        $parsed['is_anticipation'],
                        $ravDiff
                    );
                }
            }
            return;
        }

        // 2. Recebimento de Cliente / Pedido PDV
        if ($type === ConciliationType::RECEIVABLE->value && !empty($data['existing_receivable_id'])) {
            $rawTarget = (string) $data['existing_receivable_id'];
            if (str_starts_with($rawTarget, 'ar_')) {
                $ar = AccountReceivable::find((int) str_replace('ar_', '', $rawTarget));
                if ($ar) {
                    BankReconciliationService::finalize($statement, $ar, null, $ar->description);
                    if ($ar->customer_id) {
                        Customer::find($ar->customer_id)
                            ?->recordUsualPayer($statement->counterparty_name ?: $statement->description, $statement->document);
                    }
                }
            } elseif (str_starts_with($rawTarget, 'order_')) {
                $order = Order::find((int) str_replace('order_', '', $rawTarget));
                if ($order) {
                    $ar = AccountReceivable::create([
                        'order_id'        => $order->id,
                        'bank_account_id' => $statement->bank_account_id,
                        'customer_id'     => $order->customer_id,
                        'description'     => "Recebimento Pedido #{$order->id} - " . ($order->customer_name ?: 'Cliente'),
                        'payment_method'  => 'Pix / Transferência',
                        'installment'     => '1/1',
                        'gross_amount'    => abs((float) $statement->amount),
                        'fee_amount'      => 0,
                        'net_amount'      => abs((float) $statement->amount),
                        'due_date'        => $statement->bank_date,
                        'status'          => 'pending',
                    ]);
                    BankReconciliationService::finalize($statement, $ar, null, $ar->description);
                    if ($order->customer_id) {
                        Customer::find($order->customer_id)
                            ?->recordUsualPayer($statement->counterparty_name ?: $statement->description, $statement->document);
                    }
                }
            }
            return;
        }

        // 3. Entrada Não-Operacional (Empréstimo / Aporte / Estorno)
        if ($type === ConciliationType::CAPITAL_IN->value) {
            $cData = $data['capital_new'] ?? [];
            $prefix = match ($cData['kind'] ?? 'other_in') {
                'loan'       => '[Empréstimo/Giro] ',
                'partner_in' => '[Aporte Sócio] ',
                'refund'     => '[Estorno/Devolução] ',
                default      => '[Entrada Extra] ',
            };
            $desc = $prefix . ($cData['description'] ?: ($statement->counterparty_name ?: $statement->description));
            $account = BankAccount::find($statement->bank_account_id);
            if ($account) {
                BankReconciliationService::finalize($statement, $account, null, $desc);
            }
            return;
        }

        $conciliable = null;
        $supplierId = null;
        $description = null;
        $category = null;
        $subcategory = null;

        $searchTerm = self::extractCanonicalTerm($statement);

        if (in_array($type, [ConciliationType::TRANSFER->value, 'transferencia', 'transfer'], true)) {
            $tData = $data['transfer_new'] ?? [];
            if (!empty($tData['target_account_id'])) {
                $conciliable = BankAccount::find($tData['target_account_id']);
                $description = $tData['description'] ?? "Transferência";
            }
        } elseif ($type === ConciliationType::PAYROLL->value) {
            $pMode = (string) ($data['payroll_mode'] ?? 'avulso');
            $category = AccountingCategory::PAYROLL->value;

            if ($pMode === 'batch' && !empty($data['payroll_batch_id'])) {
                $rawBatch = (string) $data['payroll_batch_id'];
                if (str_starts_with($rawBatch, 'batch_')) {
                    $conciliable = PaymentBatch::find((int) str_replace('batch_', '', $rawBatch));
                    $description = $conciliable?->description;
                } elseif (str_starts_with($rawBatch, 'payroll_')) {
                    $conciliable = Payroll::find((int) str_replace('payroll_', '', $rawBatch));
                    $comp = $conciliable ? Carbon::parse($conciliable->reference_period)->format('m/Y') : '';
                    $description = "Folha de Pagamento ({$comp})";
                }
            } elseif ($pMode === 'payslip' && !empty($data['payslip_id'])) {
                $conciliable = Payslip::with(['employee', 'payroll'])->find((int) $data['payslip_id']);
                if ($conciliable) {
                    $empName = $conciliable->employee?->name ?? 'Colaborador';
                    $comp = $conciliable->payroll?->reference_period
                        ? Carbon::parse($conciliable->payroll->reference_period)->format('m/Y')
                        : '';
                    $description = "Salário Líquido: {$empName} ({$comp})";
                }
            } elseif ($pMode === 'avulso') {
                $pNew = $data['payroll_new'] ?? [];
                if (!empty($pNew['employee_id'])) {
                    $emp = Employee::find((int) $pNew['employee_id']);
                    $laborEnum = LaborPaymentType::tryFrom(self::normalizeType($pNew['labor_type'] ?? ''))
                        ?? LaborPaymentType::ADVANCE;
                    $subcategory = self::normalizeType($pNew['accounting_subcategory'] ?? AccountingSubcategory::PAYROLL->value);

                    $customNote = trim((string) ($pNew['description'] ?? ''));
                    $description = "{$laborEnum->getLabel()}: " . ($emp?->name ?? 'Colaborador')
                        . ($customNote !== '' ? " — {$customNote}" : '');

                    $conciliable = AccountPayable::create([
                        'description'            => $description,
                        'employee_id'            => $emp?->id,
                        'amount'                 => abs((float) $statement->amount),
                        'due_date'               => $statement->bank_date,
                        'accounting_category'    => $category,
                        'accounting_subcategory' => $subcategory,
                        'observations'           => "Natureza RH: {$laborEnum->getLabel()}",
                    ]);
                }
            }
        } elseif (in_array($type, [ConciliationType::EXPENSE->value, 'avulsa', 'expense'], true)) {
            if ($mode === 'link' && !empty($data['existing_payable_id'])) {
                $idRaw = $data["existing_payable_id"];
                if (str_starts_with($idRaw, "batch_")) {
                    $conciliable = PaymentBatch::find(str_replace("batch_", "", $idRaw));
                } else {
                    $conciliable = AccountPayable::find(str_replace("payable_", "", $idRaw));
                    $supplierId = $conciliable?->supplier_id;
                    $category = $conciliable?->accounting_category;
                    $subcategory = $conciliable?->accounting_subcategory;
                }
            } elseif ($mode === 'new' && !empty($data['avulsa_new']['amount'])) {
                $cleanData = Arr::except($data['avulsa_new'], ['is_supplier_locked', 'is_status_locked']);
                $conciliable = AccountPayable::create($cleanData);
                $supplierId = $conciliable->supplier_id;
                $category = $conciliable->accounting_category;
                $subcategory = $conciliable->accounting_subcategory;
                $description = $conciliable->description;
            }
        } elseif (in_array($type, [ConciliationType::RECURRING->value, 'fixa', 'recurring'], true)) {
            if ($mode === 'link' && !empty($data['existing_fixed_id'])) {
                $fixed = RecurringExpense::find($data["existing_fixed_id"]);
                $desc = $fixed->name . " (" . $statement->bank_date->format("M/Y") . ")";
                $category = $fixed->accounting_category;
                $subcategory = $fixed->accounting_subcategory;
                $conciliable = AccountPayable::create([
                    "description"            => $desc,
                    "amount"                 => abs((float) $statement->amount),
                    "supplier_id"            => $fixed->supplier_id,
                    "recurring_expense_id"   => $fixed->id,
                    "accounting_category"    => $category,
                    "accounting_subcategory" => $subcategory,
                    "due_date"               => $statement->bank_date,
                ]);
                $supplierId = $fixed->supplier_id;
            } elseif ($mode === 'new' && !empty($data['fixa_new']['name'])) {
                $fixed = RecurringExpense::create(Arr::except($data['fixa_new'], ['is_supplier_locked', 'is_status_locked']));
                $category = $fixed->accounting_category;
                $subcategory = $fixed->accounting_subcategory;
                $conciliable = AccountPayable::create([
                    "description"            => $fixed->name . " (Inicial)",
                    "amount"                 => abs((float) $statement->amount),
                    "supplier_id"            => $fixed->supplier_id,
                    "recurring_expense_id"   => $fixed->id,
                    "accounting_category"    => $category,
                    "accounting_subcategory" => $subcategory,
                    "due_date"               => $statement->bank_date,
                ]);
                $supplierId = $fixed->supplier_id;
            }
        } elseif (in_array($type, [ConciliationType::PERSONAL->value, 'pessoal', 'personal'], true)) {
            $category = AccountingCategory::PARTNERS->value;
            $subcategory = 'personal';

            if ($mode === 'link' && !empty($data['existing_personal_id'])) {
                $conciliable = AccountPayable::find($data['existing_personal_id']);
                $supplierId = $conciliable?->supplier_id;
            } elseif ($mode === 'new') {
                $pData = $data['pessoal_new'] ?? [];
                $supplierId = !empty($pData['supplier_id']) ? $pData['supplier_id'] : self::resolveSupplierForTerm($searchTerm);

                $desc = !empty($pData['description'])
                    ? $pData['description'] . " - " . ($statement->counterparty_name ?: $statement->description)
                    : "Uso Pessoal: " . ($statement->counterparty_name ?: $statement->description);

                $conciliable = AccountPayable::create([
                    "description"            => $desc,
                    "supplier_id"            => $supplierId,
                    "amount"                 => abs((float) $statement->amount),
                    "due_date"               => $statement->bank_date,
                    "is_personal"            => true,
                    "accounting_category"    => $category,
                    "accounting_subcategory" => $subcategory,
                ]);
                $description = $desc;
            }
        }

        // 1. Finaliza a transação ativa no motor de conciliação
        if ($conciliable) {
            BankReconciliationService::finalize($statement, $conciliable, $supplierId, $description);
        }

        // 2. Memoriza Regra Futura (se selecionado)
        if (!empty($data['remember_future_rule']) && !empty($searchTerm)) {
            $targetTypeEnum = match ($type) {
                'transferencia', 'transfer', ConciliationType::TRANSFER->value => ConciliationType::TRANSFER,
                'fixa', 'recurring', ConciliationType::RECURRING->value          => ConciliationType::RECURRING,
                'pessoal', 'personal', ConciliationType::PERSONAL->value        => ConciliationType::PERSONAL,
                default                                                         => ConciliationType::EXPENSE,
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
        $shouldResolveSimilar = !empty($data['resolve_similar_entries'])
            || !empty($data['batch_resolve'])
            || !empty($data['conciliar_semelhantes']);

        if ($shouldResolveSimilar && strlen($searchTerm) >= 3) {
            $sign = $statement->amount < 0 ? '<' : '>';

            $similarPending = BankStatement::query()
                ->where('status', 'pending')
                ->where('id', '!=', $statement->id)
                ->where('amount', $sign, 0)
                ->where(function ($q) use ($searchTerm) {
                    $q->where('counterparty_name', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%");
                })
                ->get();

            foreach ($similarPending as $simStmt) {
                $simDesc = $description ?: ($simStmt->counterparty_name ?: $simStmt->description);

                if (in_array($type, [ConciliationType::PERSONAL->value, 'pessoal', 'personal'], true)) {
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
                } elseif (in_array($type, [ConciliationType::RECURRING->value, 'fixa', 'recurring'], true)) {
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
                } elseif (in_array($type, [ConciliationType::EXPENSE->value, 'avulsa', 'expense'], true)) {
                    $simPayable = AccountPayable::create([
                        "description"            => $simDesc,
                        "supplier_id"            => $supplierId,
                        "amount"                 => abs((float) $simStmt->amount),
                        "due_date"               => $simStmt->bank_date,
                        "accounting_category"    => $category,
                        "accounting_subcategory" => $subcategory,
                    ]);
                    BankReconciliationService::finalize($simStmt, $simPayable, $supplierId, $simDesc);
                } elseif (in_array($type, [ConciliationType::TRANSFER->value, 'transferencia', 'transfer'], true) && $conciliable instanceof BankAccount) {
                    $transfDesc = $description ?: ($simStmt->counterparty_name ?: "Transferência / Pgto de Fatura");
                    BankReconciliationService::finalize($simStmt, $conciliable, null, $transfDesc);
                }
            }
        }
    }

    public static function extractCanonicalTerm(BankStatement $statement): string
    {
        $raw = (string) ($statement->counterparty_name ?: $statement->description);
        $clean = preg_replace('/^(Débito:\s*|COMPRAS NACIONAIS-[A-Z0-9]+\s+)/i', '', $raw);
        $clean = preg_replace('/\s+(BALNEARIO\s+BARBR|JOINVILLE\s+BR|ARAQUARI\s+BR|SAO\s+FRANCISCOBR|OSASCO\s+BR|BALNE\s+BR|BR)$/i', '', $clean);

        $term = trim($clean);
        $words = explode(' ', $term);

        if (count($words) >= 2) {
            return $words[0] . ' ' . $words[1];
        }

        return $term;
    }

    public static function resolveSupplierForTerm(string $term): int
    {
        if (strlen($term) >= 4) {
            $found = Supplier::where('name', 'like', "%{$term}%")
                ->orWhere('document_name', 'like', "%{$term}%")
                ->first();

            if ($found) return $found->id;

            $termAscii = Str::ascii($term);
            $allSuppliers = Supplier::where('is_active', true)->get(['id', 'name', 'document_name']);

            foreach ($allSuppliers as $sup) {
                $supAscii = Str::ascii($sup->name . ' ' . ($sup->document_name ?? ''));
                if (str_contains(mb_strtolower($supAscii), mb_strtolower($termAscii))) {
                    return $sup->id;
                }
            }
        }

        return Supplier::firstOrCreate(
            ['name' => 'Uso Pessoal / Sócios'],
            [
                'type'                   => 'physical',
                'is_personal'            => true,
                'accounting_category'    => AccountingCategory::PARTNERS->value,
                'accounting_subcategory' => 'personal',
                'is_active'              => true,
            ]
        )->id;
    }
}
