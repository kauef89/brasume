<?php

namespace App\Services\Financial;

use App\Enums\AccountingCategory;
use App\Models\AccountPayable;
use App\Models\Order;
use Carbon\Carbon;

class DreService
{
    protected Carbon $startDate;
    protected Carbon $endDate;
    protected string $regime; // 'competencia' ou 'caixa'

    public function __construct(?Carbon $startDate = null, ?Carbon $endDate = null, string $regime = 'competencia')
    {
        $this->startDate = $startDate ?? Carbon::now()->startOfMonth();
        $this->endDate = $endDate ?? Carbon::now()->endOfMonth();
        $this->regime = $regime;
    }

    public function calculate(): array
    {
        // 1. RECEITA OPERACIONAL BRUTA
        $currentRevenue = (float) Order::query()
            ->whereBetween('created_at', [$this->startDate->startOfDay(), $this->endDate->endOfDay()])
            ->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'draft'])
            ->sum('total');

        $legacyRevenue = 0.0;
        if (class_exists(\App\Models\LegacyOrder::class)) {
            $legacyRevenue = (float) \App\Models\LegacyOrder::query()
                ->whereBetween('created_at', [$this->startDate->startOfDay(), $this->endDate->endOfDay()])
                ->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'draft', 'trash', 'failed', 'refunded'])
                ->sum('total_value');
        }

        $grossRevenue = $currentRevenue + $legacyRevenue;

        // 2. DEDUÇÕES DA RECEITA BRUTA (Tributos Diretos e Taxas de Cartão)
        $taxesAmount = $this->getPayablesSum(categories: [AccountingCategory::ADMINISTRATIVE->value], subcategories: ['taxes', 'impostos']);
        $cardFeesAmount = $this->getPayablesSum(categories: [AccountingCategory::ADMINISTRATIVE->value], subcategories: ['bank_fees', 'taxas']);

        if ($taxesAmount == 0 && $grossRevenue > 0) {
            $taxesAmount = $grossRevenue * 0.045; // DAS Simples Nacional (~4,5%)
        }
        if ($cardFeesAmount == 0 && $grossRevenue > 0) {
            $cardFeesAmount = $grossRevenue * 0.022; // Taxa média maquininhas (~2,2%)
        }

        $totalDeductions = $taxesAmount + $cardFeesAmount;
        $netRevenue = max(0, $grossRevenue - $totalDeductions);

        // 3. CMV REAL (Insumos + Embalagens)
        $ingredientsAmount = $this->getPayablesSum(categories: [AccountingCategory::INGREDIENTS->value]);
        $packagingAmount   = $this->getPayablesSum(categories: [AccountingCategory::PACKAGING->value]);
        $cogsTotal         = $ingredientsAmount + $packagingAmount;

        // 4. MARGEM DE CONTRIBUIÇÃO BRUTA
        $contributionMargin = $netRevenue - $cogsTotal;
        $contributionMarginRatio = $netRevenue > 0 ? ($contributionMargin / $netRevenue) : 0;

        // 5. DESPESAS OPERACIONAIS FIXAS E DE ESTRUTURA
        $utilitiesAmount   = $this->getPayablesSum(categories: [AccountingCategory::UTILITIES->value]);
        $adminAmount       = $this->getPayablesSum(categories: [AccountingCategory::ADMINISTRATIVE->value], excludedSubcategories: ['taxes', 'impostos', 'bank_fees', 'taxas']);
        $maintenanceAmount = $this->getPayablesSum(categories: [AccountingCategory::MAINTENANCE->value]);
        $fixedStructure    = $utilitiesAmount + $adminAmount + $maintenanceAmount;

        // 6. PESSOAL E EQUIPE
        $payrollCosts = $this->getPayablesSum(categories: [AccountingCategory::PAYROLL->value]);

        // 7. MARKETING
        $marketingCosts = $this->getPayablesSum(categories: [AccountingCategory::MARKETING->value]);

        // 8. DESPESAS NÃO CLASSIFICADAS (Auditoria Contábil - Garante 100% de transparência)
        $uncategorizedCosts = $this->getUncategorizedPayablesSum();

        $totalOperatingExpenses = $fixedStructure + $payrollCosts + $marketingCosts + $uncategorizedCosts;

        // 9. RESULTADO OPERACIONAL (EBITDA)
        $operatingProfit = $contributionMargin - $totalOperatingExpenses;
        $operatingMarginRatio = $grossRevenue > 0 ? ($operatingProfit / $grossRevenue) : 0;

        // 10. INVESTIMENTOS EM ATIVOS / CAPEX (Isolados do EBITDA - Ex: Fachada Lumiplack)
        $capexAmount = $this->getPayablesSum(categories: [AccountingCategory::CAPEX->value]);

        // 11. RETIRADAS PESSOAIS DOS SÓCIOS
        $personalExpenses = $this->getPersonalPayablesSum();

        // 12. RESULTADO LÍQUIDO RETIDO NO CAIXA DA EMPRESA
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
            ],
            'operating_profit' => [
                'amount' => $operatingProfit,
                'margin' => round($operatingMarginRatio * 100, 2),
            ],
            'capex' => [
                'total' => $capexAmount,
            ],
            'personal_expenses' => [
                'total' => $personalExpenses,
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
        ];
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
}
