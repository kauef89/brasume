<?php

namespace App\Services\Financial;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\EmploymentType;
use App\Models\AccountPayable;
use App\Models\Employee;
use App\Models\Order;
use App\Models\RecurringExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DreService
{
    protected Carbon $startDate;
    protected Carbon $endDate;
    protected string $regime; // 'competencia' ou 'caixa'

    public function __construct(?Carbon $startDate = null, ?Carbon $endDate = null, string $regime = 'competencia')
    {
        $this->startDate = $startDate ? $startDate->copy()->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $this->endDate   = $endDate ? $endDate->copy()->endOfDay() : Carbon::now()->endOfMonth()->endOfDay();
        $this->regime    = $regime;
    }

    public function calculate(): array
    {
        // 1. RECEITA OPERACIONAL BRUTA
        $currentRevenue = (float) Order::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'draft'])
            ->sum('total');

        $legacyRevenue = 0.0;
        if (class_exists(\App\Models\LegacyOrder::class)) {
            $legacyRevenue = (float) \App\Models\LegacyOrder::query()
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'draft', 'trash', 'failed', 'refunded'])
                ->sum('total_value');
        }

        $grossRevenue = $currentRevenue + $legacyRevenue;

        // 2. DEDUÇÕES DA RECEITA BRUTA
        $realizedTaxes = $this->getPayablesSum(categories: [AccountingCategory::ADMINISTRATIVE->value], subcategories: ['taxes', 'impostos']);
        $realizedCardFees = $this->getPayablesSum(categories: [AccountingCategory::ADMINISTRATIVE->value], subcategories: ['bank_fees', 'taxas']);

        $projectedTaxes = $grossRevenue * 0.0478; // Alíquota média efetiva Simples Nacional (~4,78%)
        $projectedCardFees = $grossRevenue * 0.022; // Taxa média maquininhas (~2,2%)

        $taxesAmount = $realizedTaxes > 0 ? $realizedTaxes : $projectedTaxes;
        $cardFeesAmount = $realizedCardFees > 0 ? $realizedCardFees : $projectedCardFees;
        $totalDeductions = $taxesAmount + $cardFeesAmount;
        $netRevenue = max(0, $grossRevenue - $totalDeductions);

        // 3. CMV REAL (Insumos + Embalagens)
        $ingredientsAmount = $this->getPayablesSum(categories: [AccountingCategory::INGREDIENTS->value]);
        $packagingAmount   = $this->getPayablesSum(categories: [AccountingCategory::PACKAGING->value]);
        $cogsTotal         = $ingredientsAmount + $packagingAmount;

        // 4. MARGEM DE CONTRIBUIÇÃO BRUTA
        $contributionMargin = $netRevenue - $cogsTotal;
        $contributionMarginRatio = $netRevenue > 0 ? ($contributionMargin / $netRevenue) : 0;

        // 5. PESSOAL E EQUIPE OPERACIONAL (CLT + Diaristas - EXCLUI SÓCIOS)
        $payrollForecast = $this->calculatePayrollForecast();
        $payrollRealized = $this->getOperationalPayrollRealized();

        // Em regime de competência, se os salários principais do mês ainda não venceram, apropria o provisionado
        $payrollCosts = ($this->regime === 'competencia')
            ? max($payrollRealized, $payrollForecast['total_operational'])
            : $payrollRealized;

        // 6. UTILIDADES E OCUPAÇÃO (Água, Luz, Gás, Internet)
        $utilitiesRealized = $this->getPayablesSum(categories: [AccountingCategory::UTILITIES->value]);
        $utilitiesProjected = $this->projectCategoryExpenses(AccountingCategory::UTILITIES->value, $utilitiesRealized);
        $utilitiesAmount = ($this->regime === 'competencia')
            ? max($utilitiesRealized, $utilitiesProjected)
            : $utilitiesRealized;

        // 7. ADMINISTRATIVO E SISTEMAS
        $adminRealized = $this->getPayablesSum(categories: [AccountingCategory::ADMINISTRATIVE->value], excludedSubcategories: ['taxes', 'impostos', 'bank_fees', 'taxas']);
        $adminProjected = $this->projectCategoryExpenses(AccountingCategory::ADMINISTRATIVE->value, $adminRealized);
        $adminAmount = ($this->regime === 'competencia')
            ? max($adminRealized, $adminProjected)
            : $adminRealized;

        // 8. MANUTENÇÃO E LIMPEZA
        $maintenanceRealized = $this->getPayablesSum(categories: [AccountingCategory::MAINTENANCE->value]);
        $maintenanceProjected = $this->projectCategoryExpenses(AccountingCategory::MAINTENANCE->value, $maintenanceRealized);
        $maintenanceAmount = ($this->regime === 'competencia')
            ? max($maintenanceRealized, $maintenanceProjected)
            : $maintenanceRealized;

        // 9. MARKETING
        $marketingRealized = $this->getPayablesSum(categories: [AccountingCategory::MARKETING->value]);
        $marketingCosts = $marketingRealized;

        // 10. NÃO CLASSIFICADAS
        $uncategorizedCosts = $this->getUncategorizedPayablesSum();

        $fixedStructure = $utilitiesAmount + $adminAmount + $maintenanceAmount;
        $totalOperatingExpenses = $fixedStructure + $payrollCosts + $marketingCosts + $uncategorizedCosts;

        // 11. RESULTADO OPERACIONAL (EBITDA)
        $operatingProfit = $contributionMargin - $totalOperatingExpenses;
        $operatingMarginRatio = $grossRevenue > 0 ? ($operatingProfit / $grossRevenue) : 0;

        // 12. CAPEX E RETIRADAS DOS SÓCIOS (ISOLADOS ABAIXO DO EBITDA)
        $capexAmount = $this->getPayablesSum(categories: [AccountingCategory::CAPEX->value]);
        
        // Retiradas dos Sócios: Contas pessoais pagas pela empresa + Pró-labore
        $personalExpensesRealized = $this->getPersonalPayablesSum();
        $personalExpenses = ($this->regime === 'competencia')
            ? max($personalExpensesRealized, $payrollForecast['partners_prolabore'])
            : $personalExpensesRealized;

        $retainedNetProfit = $operatingProfit - $capexAmount - $personalExpenses;

        // 13. PONTO DE EQUILÍBRIO (BREAK-EVEN)
        $fixedForBreakEven = $fixedStructure + $payrollCosts;
        $breakEvenRevenue = $contributionMarginRatio > 0 ? ($fixedForBreakEven / $contributionMarginRatio) : 0;
        $daysInPeriod = max(1, $this->startDate->diffInDays($this->endDate) + 1);
        $dailyBreakEven = $breakEvenRevenue / $daysInPeriod;

        $safetyMargin = $grossRevenue > 0 && $breakEvenRevenue > 0
            ? (($grossRevenue - $breakEvenRevenue) / $grossRevenue) * 100
            : 0;

        $breakEvenProgress = $breakEvenRevenue > 0
            ? min(100, round(($grossRevenue / $breakEvenRevenue) * 100, 1))
            : 0;

        // 14. ESTRATIFICAÇÕES DETALHADAS (DRILL-DOWN)
        $cogsBreakdown = $this->getSectionBreakdown([AccountingCategory::INGREDIENTS->value, AccountingCategory::PACKAGING->value]);
        $structureBreakdown = $this->getSectionBreakdown(
            [AccountingCategory::UTILITIES->value, AccountingCategory::ADMINISTRATIVE->value, AccountingCategory::MAINTENANCE->value],
            excludedSubcategories: ['taxes', 'impostos', 'bank_fees', 'taxas']
        );
        $payrollBreakdown = $this->getPayrollBreakdown($payrollForecast, $payrollRealized);
        $uncategorizedBreakdown = $this->getUncategorizedBreakdown();
        $personalBreakdown = $this->getPersonalBreakdown($payrollForecast['partners_prolabore'], $personalExpensesRealized);

        // 15. NÓ COMPARATIVO (PROJETADO vs. REALIZADO)
        $comparative = [
            'revenue' => [
                'gross' => ['projected' => $grossRevenue, 'realized' => $grossRevenue, 'variance' => 0.0],
                'deductions' => [
                    'taxes'     => ['projected' => $projectedTaxes,    'realized' => $realizedTaxes,    'variance' => $realizedTaxes - $projectedTaxes],
                    'card_fees' => ['projected' => $projectedCardFees, 'realized' => $realizedCardFees, 'variance' => $realizedCardFees - $projectedCardFees],
                ],
                'net' => ['projected' => $netRevenue, 'realized' => $netRevenue, 'variance' => 0.0],
            ],
            'cogs' => [
                'ingredients' => ['realized' => $ingredientsAmount],
                'packaging'   => ['realized' => $packagingAmount],
                'total'       => ['realized' => $cogsTotal],
            ],
            'expenses' => [
                'payroll'     => [
                    'projected' => $payrollForecast['total_operational'],
                    'realized'  => $payrollRealized,
                    'variance'  => $payrollRealized - $payrollForecast['total_operational'],
                    'details'   => $payrollForecast,
                ],
                'utilities'   => [
                    'projected' => $utilitiesProjected,
                    'realized'  => $utilitiesRealized,
                    'variance'  => $utilitiesRealized - $utilitiesProjected,
                ],
                'admin'       => [
                    'projected' => $adminProjected,
                    'realized'  => $adminRealized,
                    'variance'  => $adminRealized - $adminProjected,
                ],
                'maintenance' => [
                    'projected' => $maintenanceProjected,
                    'realized'  => $maintenanceRealized,
                    'variance'  => $maintenanceRealized - $maintenanceProjected,
                ],
                'marketing'   => [
                    'projected' => $marketingCosts,
                    'realized'  => $marketingRealized,
                    'variance'  => 0.0,
                ],
            ],
            'operating_profit' => [
                'projected' => $contributionMargin - ($fixedStructure + $payrollForecast['total_operational'] + $marketingCosts + $uncategorizedCosts),
                'realized'  => $contributionMargin - ($utilitiesRealized + $adminRealized + $maintenanceRealized + $payrollRealized + $marketingCosts + $uncategorizedCosts),
            ],
        ];

        return [
            'period' => [
                'start'  => $this->startDate->format('d/m/Y'),
                'end'    => $this->endDate->format('d/m/Y'),
                'days'   => $daysInPeriod,
                'regime' => $this->regime,
            ],
            'revenue' => [
                'gross' => $grossRevenue,
                'deductions' => [
                    'taxes'     => $taxesAmount,
                    'card_fees' => $cardFeesAmount,
                    'total'     => $totalDeductions,
                ],
                'net' => $netRevenue,
            ],
            'cogs' => [
                'ingredients' => $ingredientsAmount,
                'packaging'   => $packagingAmount,
                'total'       => $cogsTotal,
                'breakdown'   => $cogsBreakdown,
            ],
            'contribution_margin' => [
                'amount' => $contributionMargin,
                'ratio'  => round($contributionMarginRatio * 100, 2),
            ],
            'expenses' => [
                'utilities'     => $utilitiesAmount,
                'admin'         => $adminAmount,
                'maintenance'   => $maintenanceAmount,
                'payroll'       => $payrollCosts,
                'marketing'     => $marketingCosts,
                'uncategorized' => $uncategorizedCosts,
                'total_fixed'   => $totalOperatingExpenses,
                'structure_breakdown'     => $structureBreakdown,
                'payroll_breakdown'       => $payrollBreakdown,
                'uncategorized_breakdown' => $uncategorizedBreakdown,
            ],
            'operating_profit' => [
                'amount' => $operatingProfit,
                'margin' => round($operatingMarginRatio * 100, 2),
            ],
            'capex' => [
                'total' => $capexAmount,
                'breakdown' => $this->getSectionBreakdown([AccountingCategory::CAPEX->value]),
            ],
            'personal_expenses' => [
                'total' => $personalExpenses,
                'breakdown' => $personalBreakdown,
            ],
            'retained_profit' => $retainedNetProfit,
            'break_even' => [
                'target'        => $breakEvenRevenue,
                'daily'         => $dailyBreakEven,
                'progress'      => $breakEvenProgress,
                'safety_margin' => round($safetyMargin, 1),
                'reached'       => $grossRevenue >= $breakEvenRevenue,
                'difference'    => $grossRevenue - $breakEvenRevenue,
            ],
            'comparative' => $comparative,
        ];
    }

    /**
     * Projeta o custo de pessoal respeitando estritamente o EmploymentType
     */
    protected function calculatePayrollForecast(): array
    {
        // 1. Mensalistas CLT
        $cltBase = (float) Employee::query()
            ->where('is_active', true)
            ->where('employment_type', EmploymentType::CLT->value)
            ->sum('base_remuneration');

        $provisions = $cltBase * 0.20; // 20% para provisões de 13º, férias e FGTS

        // 2. Estagiários e PJs operacionais (se houver)
        $otherOperational = (float) Employee::query()
            ->where('is_active', true)
            ->whereIn('employment_type', [EmploymentType::INTERN->value, EmploymentType::PJ->value])
            ->sum('base_remuneration');

        // 3. Pró-labore dos Sócios (alocado abaixo do EBITDA)
        $partnersProLabore = (float) Employee::query()
            ->where('is_active', true)
            ->where('employment_type', EmploymentType::PARTNER->value)
            ->sum('base_remuneration');

        // 4. Freelancers e Diaristas baseados na Escala de Trabalho
        $freelancerTypes = [
            EmploymentType::FREELANCER->value,
            EmploymentType::FREELANCE_FIXED->value,
        ];

        $scheduledShifts = 0;
        $freelancerAmount = 0.0;

        if (DB::getSchemaBuilder()->hasTable('employee_schedules')) {
            $schedules = DB::table('employee_schedules')
                ->join('employees', 'employees.id', '=', 'employee_schedules.employee_id')
                ->whereBetween('employee_schedules.date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
                ->where('employee_schedules.is_day_off', false)
                ->where('employee_schedules.status', '!=', 'cancelled')
                ->whereIn('employees.employment_type', $freelancerTypes)
                ->select('employees.base_remuneration')
                ->get();

            $scheduledShifts = $schedules->count();

            if ($scheduledShifts > 0) {
                foreach ($schedules as $s) {
                    $val = (float) $s->base_remuneration;
                    $freelancerAmount += ($val > 0 ? $val : 100.00);
                }
            } else {
                $freelancerAmount = (float) AccountPayable::query()
                    ->where('is_personal', false)
                    ->where('accounting_category', AccountingCategory::PAYROLL->value)
                    ->where('accounting_subcategory', 'freelancers')
                    ->whereBetween('due_date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
                    ->sum('amount');
            }
        }

        $totalOperational = $cltBase + $provisions + $otherOperational + $freelancerAmount;

        return [
            'clt_base'            => $cltBase,
            'provisions'          => $provisions,
            'other_operational'   => $otherOperational,
            'freelancers'         => $freelancerAmount,
            'freelancer_shifts'   => $scheduledShifts,
            'total_operational'   => $totalOperational,
            'partners_prolabore'  => $partnersProLabore,
        ];
    }

    /**
     * Busca o montante de folha operacional reconhecido no Contas a Pagar
     */
    protected function getOperationalPayrollRealized(): float
    {
        $partnerEmployeeIds = Employee::where('employment_type', EmploymentType::PARTNER->value)->pluck('id')->toArray();

        $query = AccountPayable::query()
            ->where('is_personal', false)
            ->where('accounting_category', AccountingCategory::PAYROLL->value)
            ->where(function ($q) use ($partnerEmployeeIds) {
                $q->whereNull('employee_id')
                  ->orWhereNotIn('employee_id', $partnerEmployeeIds);
            });

        if ($this->regime === 'caixa') {
            return (float) $query->where('status', 'paid')
                ->whereBetween('payment_date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
                ->sum('amount');
        }

        $nextMonthCutoff = $this->endDate->copy()->addDays(10)->toDateString();

        return (float) $query->where(function ($q) use ($nextMonthCutoff) {
            $q->whereBetween('due_date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
              ->orWhere(function ($sub) use ($nextMonthCutoff) {
                  $sub->whereBetween('due_date', [$this->endDate->toDateString(), $nextMonthCutoff])
                      ->whereIn('accounting_subcategory', ['payroll', 'salaries', 'freelancers']);
              });
        })->sum('amount');
    }

    /**
     * Projeta despesas com base no cadastro de recorrências e médias históricas de contas de consumo
     */
    protected function projectCategoryExpenses(string $category, float $realizedAmount): float
    {
        $recurringTotal = (float) RecurringExpense::query()
            ->where('accounting_category', $category)
            ->where('is_active', true)
            ->where('is_personal', false)
            ->sum('default_amount');

        if ($category === AccountingCategory::UTILITIES->value && $realizedAmount < 500.00) {
            $recurringTotal = max($recurringTotal, 1450.00);
        }

        return max($realizedAmount, $recurringTotal);
    }

    protected function getPayablesSum(array $categories = [], array $subcategories = [], array $excludedSubcategories = []): float
    {
        $query = $this->getBasePayablesQuery()->where('is_personal', false);

        if (!empty($categories)) {
            $query->whereIn('accounting_category', $categories);
        }

        if (!empty($subcategories)) {
            $query->whereIn('accounting_subcategory', $subcategories);
        }

        if (!empty($excludedSubcategories)) {
            $query->whereNotIn('accounting_subcategory', $excludedSubcategories);
        }

        return (float) $query->sum('amount');
    }

    protected function getUncategorizedPayablesSum(): float
    {
        return (float) $this->getBasePayablesQuery()
            ->where('is_personal', false)
            ->where(function ($q) {
                $q->whereNull('accounting_category')
                  ->orWhere('accounting_category', '')
                  ->orWhere('accounting_category', 'like', '%SEM%');
            })
            ->sum('amount');
    }

    protected function getPersonalPayablesSum(): float
    {
        return (float) $this->getBasePayablesQuery()
            ->where(function ($q) {
                $q->where('is_personal', true)
                  ->orWhere('accounting_category', AccountingCategory::PARTNERS->value)
                  ->orWhere('accounting_category', 'socios');
            })
            ->sum('amount');
    }

    protected function getBasePayablesQuery()
    {
        $query = AccountPayable::query();

        if ($this->regime === 'caixa') {
            return $query->where('status', 'paid')
                ->whereBetween('payment_date', [$this->startDate->toDateString(), $this->endDate->toDateString()]);
        }

        return $query->whereBetween('due_date', [$this->startDate->toDateString(), $this->endDate->toDateString()]);
    }

    /**
     * Estratificação por Natureza, Fornecedor, Subcategoria e Títulos
     */
            public function getSectionBreakdown(array $categories, array $subcategories = [], array $excludedSubcategories = []): array
    {
        $query = $this->getBasePayablesQuery()
            ->with(['supplier', 'employee', 'recurringExpense'])
            ->where('is_personal', false);

        if (!empty($categories)) {
            $query->whereIn('accounting_category', $categories);
        }
        if (!empty($subcategories)) {
            $query->whereIn('accounting_subcategory', $subcategories);
        }
        if (!empty($excludedSubcategories)) {
            $query->whereNotIn('accounting_subcategory', $excludedSubcategories);
        }

        $payables = $query->orderBy('due_date', 'asc')->get();

        // 1. Contratos ativos da seção
        $activeRecurring = RecurringExpense::with('supplier')
            ->whereIn('accounting_category', $categories)
            ->where('is_active', true)
            ->where('is_personal', false)
            ->orderBy('due_day', 'asc')
            ->get();

        $contracts = [];
        $consumedPayableIds = [];

        foreach ($activeRecurring as $rec) {
            $matchedPayables = $payables->filter(function ($p) use ($rec, $consumedPayableIds) {
                if (in_array($p->id, $consumedPayableIds)) return false;
                if ($p->recurring_expense_id === $rec->id) return true;
                if ($rec->supplier_id && $p->supplier_id === $rec->supplier_id) return true;
                return false;
            });

            if ($matchedPayables->isNotEmpty()) {
                foreach ($matchedPayables as $matchedPayable) {
                    $consumedPayableIds[] = $matchedPayable->id;
                    $isPaid = $matchedPayable->status === 'paid';
                    $contracts[] = [
                        'id'           => $rec->id,
                        'name'         => $rec->name,
                        'supplier'     => $rec->supplier?->name ?: ($rec->employee?->name ?: ($matchedPayable->supplier?->name ?: ($matchedPayable->employee?->name ?: 'Colaborador / Diarista'))),
                        'due_day'      => $matchedPayable->due_date ? $matchedPayable->due_date->format('d/m') : sprintf('%02d/%s', $rec->due_day, $this->startDate->format('m')),
                        'amount'       => (float) $matchedPayable->amount,
                        'status'       => $isPaid ? 'paid' : 'pending',
                        'status_label' => $isPaid ? 'PAGO' : 'PENDENTE',
                        'payable_id'   => $matchedPayable->id,
                    ];
                }
            } else {
                $contracts[] = [
                    'id'           => $rec->id,
                    'name'         => $rec->name,
                    'supplier'     => $rec->supplier?->name ?: 'Fornecedor Contratado',
                    'due_day'      => sprintf('%02d/%s', $rec->due_day, $this->startDate->format('m')),
                    'amount'       => (float) $rec->default_amount,
                    'status'       => 'provisioned',
                    'status_label' => 'A VENCER (PROVISÃO)',
                    'payable_id'   => null,
                ];
            }
        }

        $punctualItems = $payables->filter(fn($p) => !in_array($p->id, $consumedPayableIds));
        $recurringTotalAmount = collect($contracts)->sum('amount');

        $byNature = [
            'recurring' => [
                'label'     => 'Contratos / Recorrências',
                'amount'    => (float) $recurringTotalAmount,
                'count'     => count($contracts),
                'contracts' => $contracts,
            ],
            'punctual' => [
                'label'  => 'Compras & Gastos Pontuais',
                'amount' => (float) $punctualItems->sum('amount'),
                'count'  => $punctualItems->count(),
            ],
        ];

        // 2. Agrupamento por Fornecedor (inclui contratos provisionados)
        $supplierGroups = [];
        foreach ($payables as $p) {
            $sName = $p->supplier?->name ?: ($p->supplier?->document_name ?: ($p->employee?->name ?: 'Não Identificado'));
            if (!isset($supplierGroups[$sName])) {
                $supplierGroups[$sName] = ['name' => $sName, 'amount' => 0.0, 'count' => 0, 'paid' => 0.0, 'pending' => 0.0];
            }
            $supplierGroups[$sName]['amount'] += (float) $p->amount;
            $supplierGroups[$sName]['count']++;
            if ($p->status === 'paid') {
                $supplierGroups[$sName]['paid'] += (float) $p->amount;
            } else {
                $supplierGroups[$sName]['pending'] += (float) $p->amount;
            }
        }

        foreach ($contracts as $c) {
            if ($c['status'] === 'provisioned') {
                $sName = $c['supplier'];
                if (!isset($supplierGroups[$sName])) {
                    $supplierGroups[$sName] = ['name' => $sName, 'amount' => 0.0, 'count' => 0, 'paid' => 0.0, 'pending' => 0.0];
                }
                $supplierGroups[$sName]['amount'] += (float) $c['amount'];
                $supplierGroups[$sName]['count']++;
                $supplierGroups[$sName]['pending'] += (float) $c['amount'];
            }
        }
        $bySupplier = collect($supplierGroups)->sortByDesc('amount')->values()->all();

        // 3. Subcategorias
        $bySubcategory = $payables->groupBy('accounting_subcategory')
            ->map(function ($items, $subKey) {
                $amt = (float) $items->sum('amount');
                $subEnum = AccountingSubcategory::tryFrom($subKey ?? '');
                $label = $subEnum ? $subEnum->getLabel() : ($subKey ? ucfirst($subKey) : 'Geral');
                return [
                    'key'    => $subKey,
                    'label'  => $label,
                    'amount' => $amt,
                    'count'  => $items->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();

        $items = $payables->map(fn($p) => [
            'id'           => $p->id,
            'description'  => $p->description,
            'supplier'     => $p->supplier?->name ?: ($p->employee?->name ?: 'S/F'),
            'amount'       => (float) $p->amount,
            'due_date'     => $p->due_date ? $p->due_date->format('d/m/Y') : '-',
            'payment_date' => $p->payment_date ? $p->payment_date->format('d/m/Y') : '-',
            'status'       => $p->status,
            'is_recurrent' => !is_null($p->recurring_expense_id),
        ])->values()->all();

        return [
            'total'          => (float) ($punctualItems->sum('amount') + $recurringTotalAmount),
            'by_nature'      => $byNature,
            'contracts'      => $contracts,
            'by_supplier'    => $bySupplier,
            'by_subcategory' => $bySubcategory,
            'items'          => $items,
        ];
    }

    public function getPayrollBreakdown(array $forecast, float $realized): array
    {
        $query = AccountPayable::query()
            ->with(['employee', 'supplier'])
            ->where('is_personal', false)
            ->where('accounting_category', AccountingCategory::PAYROLL->value);

        if ($this->regime === 'caixa') {
            $payables = $query->where('status', 'paid')
                ->whereBetween('payment_date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
                ->get();
        } else {
            $nextMonthCutoff = $this->endDate->copy()->addDays(10)->toDateString();
            $payables = $query->where(function ($q) use ($nextMonthCutoff) {
                $q->whereBetween('due_date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
                  ->orWhere(function ($sub) use ($nextMonthCutoff) {
                      $sub->whereBetween('due_date', [$this->endDate->toDateString(), $nextMonthCutoff])
                          ->whereIn('accounting_subcategory', ['payroll', 'salaries', 'freelancers']);
                  });
            })->get();
        }

        $items = $payables->map(fn($p) => [
            'id'          => $p->id,
            'description' => $p->description,
            'employee'    => $p->employee?->name ?: ($p->supplier?->name ?: 'Colaborador'),
            'amount'      => (float) $p->amount,
            'due_date'    => $p->due_date ? $p->due_date->format('d/m/Y') : '-',
            'status'      => $p->status,
        ])->values()->all();

        return [
            'forecast' => $forecast,
            'realized' => $realized,
            'items'    => $items,
        ];
    }

    public function getUncategorizedBreakdown(): array
    {
        $payables = $this->getBasePayablesQuery()
            ->with('supplier')
            ->where('is_personal', false)
            ->where(function ($q) {
                $q->whereNull('accounting_category')
                  ->orWhere('accounting_category', '')
                  ->orWhere('accounting_category', 'like', '%SEM%');
            })
            ->orderBy('due_date', 'asc')
            ->get();

        return [
            'total' => (float) $payables->sum('amount'),
            'count' => $payables->count(),
            'items' => $payables->map(fn($p) => [
                'id'          => $p->id,
                'description' => $p->description,
                'supplier'    => $p->supplier?->name ?: 'A Identificar',
                'amount'      => (float) $p->amount,
                'due_date'    => $p->due_date ? $p->due_date->format('d/m/Y') : '-',
                'status'      => $p->status,
            ])->values()->all(),
        ];
    }

    public function getPersonalBreakdown(float $prolabore, float $realized): array
    {
        $payables = $this->getBasePayablesQuery()
            ->with('supplier')
            ->where(function ($q) {
                $q->where('is_personal', true)
                  ->orWhere('accounting_category', AccountingCategory::PARTNERS->value)
                  ->orWhere('accounting_category', 'socios');
            })
            ->orderBy('due_date', 'asc')
            ->get();

        $bySupplier = $payables->groupBy(fn($p) => $p->supplier?->name ?: 'Uso Pessoal')
            ->map(fn($items, $name) => [
                'name'   => $name,
                'amount' => (float) $items->sum('amount'),
                'count'  => $items->count(),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();

        return [
            'prolabore_forecast' => $prolabore,
            'realized'           => $realized,
            'by_supplier'        => $bySupplier,
            'items'              => $payables->map(fn($p) => [
                'id'          => $p->id,
                'description' => $p->description,
                'supplier'    => $p->supplier?->name ?: 'Uso Pessoal',
                'amount'      => (float) $p->amount,
                'due_date'    => $p->due_date ? $p->due_date->format('d/m/Y') : '-',
                'status'      => $p->status,
            ])->values()->all(),
        ];
    }
}
