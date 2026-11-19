<?php

namespace App\Services\Analytics;

use App\Enums\AcquirerMatchStatus;
use App\Enums\EmployeeRole;
use App\Enums\EmploymentType;
use App\Enums\ProductionOrderStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\AcquirerTransaction;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\CashShift;
use App\Models\Checklist;
use App\Models\ChecklistLog;
use App\Models\CompliancePermit;
use App\Models\EmployeeSchedule;
use App\Models\GoodsReceipt;
use App\Models\Order;
use App\Models\ProductionOrder;
use App\Models\ReceivedInvoice;
use App\Models\ShoppingListItem;
use App\Services\Financial\DreService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DailyCommandCenterService
{
    public function build(Carbon $targetDate): array
    {
        $date = $targetDate->copy()->startOfDay();
        $dateStr = $date->toDateString();
        $isToday = $date->isToday();
        $isFuture = $date->isFuture();

        // 1. VENDAS & FATURAMENTO DO DIA
        $ordersQuery = Order::query()
            ->whereDate('created_at', $dateStr)
            ->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'draft']);

        $grossRevenue = (float) (clone $ordersQuery)->sum('total');
        $ordersCount = (int) (clone $ordersQuery)->count();
        $avgTicket = $ordersCount > 0 ? ($grossRevenue / $ordersCount) : 0.0;

        $byChannel = (clone $ordersQuery)
            ->selectRaw('type, COUNT(*) as qty, COALESCE(SUM(total), 0) as total')
            ->groupBy('type')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->type => [
                    'qty'   => (int) $row->qty,
                    'total' => (float) $row->total,
                ],
            ])
            ->toArray();

        // Projeção de fechamento do dia (apenas se for hoje e já houver vendas)
        $projectedDayRevenue = $grossRevenue;
        if ($isToday && $grossRevenue > 0) {
            $activeShift = CashShift::where('status', 'open')->latest()->first();
            $startRef = $activeShift?->opened_at
                ? Carbon::parse($activeShift->opened_at)
                : $date->copy()->setTime(10, 0);

            if ($startRef->lt($date)) {
                $startRef = $date->copy()->setTime(10, 0);
            }

            $endRef = $date->copy()->setTime(23, 30);
            $elapsedMinutes = max(30, $startRef->diffInMinutes(now(), false));
            $totalWindowMinutes = max($elapsedMinutes, $startRef->diffInMinutes($endRef));

            if ($elapsedMinutes > 0 && $totalWindowMinutes > $elapsedMinutes) {
                $pacePerMinute = $grossRevenue / $elapsedMinutes;
                $projectedDayRevenue = round($pacePerMinute * $totalWindowMinutes, 2);
            }
        }

        // 2. ESCALA DE TRABALHO DO DIA & CUSTO DE FREELANCERS
        $schedules = collect();
        $daysOffCount = 0;

        if (Schema::hasTable('employee_schedules')) {
            $allDaySchedules = EmployeeSchedule::with(['employee', 'slot'])
                ->whereDate('date', $dateStr)
                ->where('status', '!=', 'cancelled')
                ->get();

            $daysOffCount = $allDaySchedules->where('is_day_off', true)->count();

            $schedules = $allDaySchedules
                ->where('is_day_off', false)
                ->sortBy(fn ($s) => ($s->slot?->sort_order ?? 99) . '_' . ($s->employee?->name ?? ''))
                ->values();
        }

        $freelancerCostsToday = 0.0;
        $freelancerCount = 0;
        $cltCount = 0;
        $teamList = [];

        foreach ($schedules as $sched) {
            $emp = $sched->employee;
            if (!$emp) {
                continue;
            }

            $empType = $emp->employment_type instanceof EmploymentType
                ? $emp->employment_type
                : EmploymentType::tryFrom((string) $emp->employment_type);

            $isFreelancer = $empType?->isFreelancer() ?? false;
            $dailyCost = 0.0;

            if ($isFreelancer) {
                $freelancerCount++;
                $base = (float) $emp->base_remuneration;
                $dailyCost = $base > 0 ? $base : 100.00;
                $freelancerCostsToday += $dailyCost;
            } else {
                $cltCount++;
            }

            $roleEnum = EmployeeRole::tryFrom((string) $sched->assigned_role)
                ?? ($emp->role instanceof EmployeeRole ? $emp->role : EmployeeRole::tryFrom((string) $emp->role));

            $teamList[] = [
                'id'             => $sched->id,
                'name'           => $emp->name,
                'role_label'     => $roleEnum?->getLabel() ?? ucfirst((string) $sched->assigned_role),
                'slot_name'      => $sched->slot?->name ?? 'Turno Geral',
                'employment'     => $empType?->getLabel() ?? 'Equipe',
                'is_freelancer'  => $isFreelancer,
                'is_extra_shift' => (bool) $sched->is_extra_shift,
                'daily_cost'     => $dailyCost,
                'status'         => $sched->status,
            ];
        }

        // 3. BREAK-EVEN DINÂMICO DO DIA (Considerando estrutura fixa + CLT rateada + Freelancers escalados HOJE)
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $daysInMonth = max(1, $monthStart->diffInDays($monthEnd) + 1);

        $dreMonth = (new DreService($monthStart, $monthEnd, 'competencia'))->calculate();

        $monthlyStructure = (float) ($dreMonth['expenses']['utilities'] ?? 0)
            + (float) ($dreMonth['expenses']['admin'] ?? 0)
            + (float) ($dreMonth['expenses']['maintenance'] ?? 0)
            + (float) ($dreMonth['expenses']['marketing'] ?? 0);

        $payrollDetails = $dreMonth['comparative']['expenses']['payroll']['details'] ?? [];
        $monthlyFixedPayroll = (float) ($payrollDetails['clt_base'] ?? 0)
            + (float) ($payrollDetails['provisions'] ?? 0)
            + (float) ($payrollDetails['other_operational'] ?? 0);

        $dailyFixedStructure = ($monthlyStructure + $monthlyFixedPayroll) / $daysInMonth;
        $totalDailyFixedAndTeam = $dailyFixedStructure + $freelancerCostsToday;

        $cmRatioPct = (float) ($dreMonth['contribution_margin']['ratio'] ?? 0);
        // Fallback saudável de 55% caso o mês ainda esteja sem histórico suficiente de compras
        $effectiveCmRatio = $cmRatioPct > 5 ? ($cmRatioPct / 100) : 0.55;

        $dailyBreakEvenTarget = $effectiveCmRatio > 0
            ? round($totalDailyFixedAndTeam / $effectiveCmRatio, 2)
            : 0.0;

        $breakEvenProgress = $dailyBreakEvenTarget > 0
            ? min(100, round(($grossRevenue / $dailyBreakEvenTarget) * 100, 1))
            : 0.0;

        $estimatedDailyNetProfit = round(($grossRevenue * $effectiveCmRatio) - $totalDailyFixedAndTeam, 2);

        // 4. CONTAS A PAGAR & RECEBER DO DIA + ATRASADOS
        $payablesDueToday = AccountPayable::with(['supplier', 'employee'])
            ->where('status', 'pending')
            ->where('is_personal', false)
            ->whereDate('due_date', $dateStr)
            ->orderByDesc('amount')
            ->get();

        $overduePayablesQuery = AccountPayable::query()
            ->where('status', 'pending')
            ->where('is_personal', false)
            ->whereDate('due_date', '<', Carbon::today()->toDateString());

        $receivablesDueToday = AccountReceivable::with(['customer', 'bankAccount'])
            ->where('status', 'pending')
            ->whereDate('due_date', $dateStr)
            ->orderByDesc('net_amount')
            ->get();

        $overdueReceivablesQuery = AccountReceivable::query()
            ->where('status', 'pending')
            ->whereDate('due_date', '<', Carbon::today()->toDateString());

        // 5. CONCILIAÇÕES PENDENTES & TESOURARIA
        $pendingStatementsCount = Schema::hasTable('bank_statements')
            ? BankStatement::where('status', 'pending')->count()
            : 0;
        $pendingStatementsAmount = Schema::hasTable('bank_statements')
            ? (float) BankStatement::where('status', 'pending')->sum('amount')
            : 0.0;

        $pendingAcquirerCount = Schema::hasTable('acquirer_transactions')
            ? AcquirerTransaction::whereIn('status', [
                AcquirerMatchStatus::ORPHAN->value,
                AcquirerMatchStatus::AMBIGUOUS->value,
            ])->count()
            : 0;
        $pendingAcquirerAmount = Schema::hasTable('acquirer_transactions')
            ? (float) AcquirerTransaction::whereIn('status', [
                AcquirerMatchStatus::ORPHAN->value,
                AcquirerMatchStatus::AMBIGUOUS->value,
            ])->sum('net_amount')
            : 0.0;

        $totalBankBalance = Schema::hasTable('bank_accounts')
            ? (float) BankAccount::liquidTreasury()->sum('current_balance')
            : 0.0;

        $openShift = CashShift::where('status', 'open')->latest()->first();

        // 5B. ÚLTIMO TURNO FECHADO (AUDITORIA & CONCILIAÇÃO IMEDIATA)
        $lastClosedShiftData = $this->buildLastClosedShiftSummary($dreMonth, $effectiveCmRatio);

        // 6. PRODUÇÕES AGENDADAS (WIP)
        $productionOrders = collect();
        if (Schema::hasTable('production_orders')) {
            $productionOrders = ProductionOrder::with('product')
                ->where(function ($q) use ($dateStr, $isToday) {
                    $q->whereDate('due_date', $dateStr);
                    if ($isToday) {
                        $q->orWhere(function ($sub) use ($dateStr) {
                            $sub->whereDate('due_date', '<', $dateStr)
                                ->whereIn('status', [
                                    ProductionOrderStatus::PENDING->value,
                                    ProductionOrderStatus::IN_PROGRESS->value,
                                ]);
                        });
                    }
                })
                ->orderBy('due_date')
                ->get();
        }

        // 7. TAREFAS / CHECKLISTS AGENDADOS PARA O DIA
        $dayOfWeek = $date->dayOfWeek; // 0 (Dom) a 6 (Sáb)
        $checklistsSummary = [];
        $totalTasksToday = 0;
        $doneTasksToday = 0;
        $issueTasksToday = 0;

        if (Schema::hasTable('checklists')) {
            $shiftForDate = $isToday
                ? $openShift
                : CashShift::whereDate('opened_at', $dateStr)->latest()->first();

            $activeChecklists = Checklist::with('tasks')
                ->where('is_active', true)
                ->get()
                ->filter(function ($cl) use ($dayOfWeek) {
                    $days = $cl->recurrence_days;
                    if (empty($days) || !is_array($days)) {
                        return true;
                    }
                    return in_array($dayOfWeek, array_map('intval', $days), true);
                });

            $logsByTask = collect();
            if ($shiftForDate && Schema::hasTable('checklist_logs')) {
                $logsByTask = ChecklistLog::where('cash_shift_id', $shiftForDate->id)
                    ->get()
                    ->keyBy('checklist_task_id');
            }

            foreach ($activeChecklists as $cl) {
                $tasksCount = $cl->tasks->count();
                if ($tasksCount === 0) {
                    continue;
                }

                $doneCount = 0;
                $issueCount = 0;
                foreach ($cl->tasks as $t) {
                    $log = $logsByTask->get($t->id);
                    if ($log) {
                        if ($log->status === 'issue') {
                            $issueCount++;
                        } else {
                            $doneCount++;
                        }
                    }
                }

                $totalTasksToday += $tasksCount;
                $doneTasksToday += $doneCount;
                $issueTasksToday += $issueCount;

                $checklistsSummary[] = [
                    'id'           => $cl->id,
                    'title'        => $cl->title,
                    'trigger_type' => $cl->trigger_type,
                    'trigger_time' => $cl->trigger_time ? substr((string) $cl->trigger_time, 0, 5) : null,
                    'target_role'  => $cl->target_role,
                    'total'        => $tasksCount,
                    'done'         => $doneCount,
                    'issues'       => $issueCount,
                ];
            }
        }

        // 8. RADAR ENTERPRISE (Suprimentos, Fiscal e Compliance)
        $criticalShoppingCount = Schema::hasTable('shopping_list_items')
            ? ShoppingListItem::where('status', 'pending')->where('urgency_level', 'critical')->count()
            : 0;
        $pendingShoppingCount = Schema::hasTable('shopping_list_items')
            ? ShoppingListItem::where('status', 'pending')->count()
            : 0;

        $pendingInvoicesCount = Schema::hasTable('received_invoices')
            ? ReceivedInvoice::whereIn('status', ['pending', 'ready', 'imported'])->count()
            : 0;

        $pendingReceiptsCount = Schema::hasTable('goods_receipts')
            ? GoodsReceipt::where('status', 'pending_invoice')->count()
            : 0;

        $expiringPermits = Schema::hasTable('compliance_permits')
            ? CompliancePermit::where('is_active', true)
                ->whereDate('expires_at', '<=', Carbon::today()->addDays(30)->toDateString())
                ->orderBy('expires_at')
                ->get()
            : collect();

        return [
            'date' => [
                'formatted' => $date->format('d/m/Y'),
                'weekday'   => ucfirst($date->locale('pt_BR')->dayName),
                'is_today'  => $isToday,
                'is_future' => $isFuture,
            ],
            'sales' => [
                'gross'      => $grossRevenue,
                'count'      => $ordersCount,
                'avg_ticket' => $avgTicket,
                'projected'  => $projectedDayRevenue,
                'by_channel' => $byChannel,
            ],
            'break_even' => [
                'target'              => $dailyBreakEvenTarget,
                'progress'            => $breakEvenProgress,
                'reached'             => $dailyBreakEvenTarget > 0 && $grossRevenue >= $dailyBreakEvenTarget,
                'remaining'           => max(0, $dailyBreakEvenTarget - $grossRevenue),
                'surplus'             => max(0, $grossRevenue - $dailyBreakEvenTarget),
                'daily_fixed'         => round($dailyFixedStructure, 2),
                'freelancers_cost'    => round($freelancerCostsToday, 2),
                'total_daily_cost'    => round($totalDailyFixedAndTeam, 2),
                'cm_ratio_pct'        => round($effectiveCmRatio * 100, 1),
                'is_cm_fallback'      => $cmRatioPct <= 5,
                'estimated_net_profit'=> $estimatedDailyNetProfit,
            ],
            'team' => [
                'total_scheduled'  => count($teamList),
                'clt_count'        => $cltCount,
                'freelancer_count' => $freelancerCount,
                'days_off_count'   => $daysOffCount,
                'freelancer_cost'  => round($freelancerCostsToday, 2),
                'members'          => $teamList,
            ],
            'last_closed_shift' => $lastClosedShiftData,
            'treasury' => [
                'bank_balance'          => $totalBankBalance,
                'open_shift'            => $openShift,
                'payables_today'        => $payablesDueToday,
                'payables_today_sum'    => (float) $payablesDueToday->sum('amount'),
                'overdue_payables_count'=> (int) (clone $overduePayablesQuery)->count(),
                'overdue_payables_sum'  => (float) (clone $overduePayablesQuery)->sum('amount'),
                'receivables_today'     => $receivablesDueToday,
                'receivables_today_sum' => (float) $receivablesDueToday->sum('net_amount'),
                'overdue_rec_count'     => (int) (clone $overdueReceivablesQuery)->count(),
                'overdue_rec_sum'       => (float) (clone $overdueReceivablesQuery)->sum('net_amount'),
            ],
            'reconciliation' => [
                'bank_pending_count'     => $pendingStatementsCount,
                'bank_pending_amount'    => $pendingStatementsAmount,
                'acquirer_pending_count' => $pendingAcquirerCount,
                'acquirer_pending_amount'=> $pendingAcquirerAmount,
            ],
            'production' => [
                'orders'        => $productionOrders,
                'pending_count' => $productionOrders->whereIn('status', ['pending', 'in_progress'])->count(),
                'done_count'    => $productionOrders->where('status', 'completed')->count(),
            ],
            'checklists' => [
                'items'        => $checklistsSummary,
                'total_tasks'  => $totalTasksToday,
                'done_tasks'   => $doneTasksToday,
                'issue_tasks'  => $issueTasksToday,
                'progress_pct' => $totalTasksToday > 0 ? round(($doneTasksToday / $totalTasksToday) * 100) : 0,
            ],
            'radar' => [
                'critical_shopping' => $criticalShoppingCount,
                'pending_shopping'  => $pendingShoppingCount,
                'pending_invoices'  => $pendingInvoicesCount,
                'pending_receipts'  => $pendingReceiptsCount,
                'expiring_permits'  => $expiringPermits,
            ],
        ];
    }

    protected function buildLastClosedShiftSummary(array $currentDreMonth, float $fallbackCmRatio): ?array
    {
        $shift = CashShift::with(['bankAccount'])
            ->where('status', '!=', 'open')
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->first();

        if (!$shift) {
            return null;
        }

        $openedAt = $shift->opened_at ? Carbon::parse($shift->opened_at) : Carbon::parse($shift->created_at);
        $closedAt = $shift->closed_at ? Carbon::parse($shift->closed_at) : $openedAt->copy()->endOfDay();
        $shiftDate = $openedAt->copy()->startOfDay();
        $shiftDateStr = $shiftDate->toDateString();

        // 1. Faturamento do turno (pedidos pagos no turno ou criados na janela do turno)
        $shiftOrdersQuery = Order::query()
            ->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'draft'])
            ->where(function ($q) use ($shift, $openedAt, $closedAt) {
                $q->whereHas('payments', fn ($pq) => $pq->where('cash_shift_id', $shift->id))
                  ->orWhereBetween('created_at', [$openedAt, $closedAt]);
            });

        $shiftRevenue = (float) (clone $shiftOrdersQuery)->sum('total');
        $shiftOrdersCount = (int) (clone $shiftOrdersQuery)->count();

        // 2. Meta de Break-Even referente ao dia daquele turno
        if ($shiftDate->isSameMonth(Carbon::today())) {
            $dreRef = $currentDreMonth;
        } else {
            $dreRef = (new DreService($shiftDate->copy()->startOfMonth(), $shiftDate->copy()->endOfMonth(), 'competencia'))->calculate();
        }

        $mStart = $shiftDate->copy()->startOfMonth();
        $mEnd = $shiftDate->copy()->endOfMonth();
        $daysInShiftMonth = max(1, $mStart->diffInDays($mEnd) + 1);

        $monthlyStructure = (float) ($dreRef['expenses']['utilities'] ?? 0)
            + (float) ($dreRef['expenses']['admin'] ?? 0)
            + (float) ($dreRef['expenses']['maintenance'] ?? 0)
            + (float) ($dreRef['expenses']['marketing'] ?? 0);

        $payrollDetails = $dreRef['comparative']['expenses']['payroll']['details'] ?? [];
        $monthlyFixedPayroll = (float) ($payrollDetails['clt_base'] ?? 0)
            + (float) ($payrollDetails['provisions'] ?? 0)
            + (float) ($payrollDetails['other_operational'] ?? 0);

        $shiftDailyFixed = ($monthlyStructure + $monthlyFixedPayroll) / $daysInShiftMonth;

        $shiftFreelancerCost = 0.0;
        if (Schema::hasTable('employee_schedules')) {
            $freelancerScheds = EmployeeSchedule::with('employee')
                ->whereDate('date', $shiftDateStr)
                ->where('is_day_off', false)
                ->where('status', '!=', 'cancelled')
                ->get();

            foreach ($freelancerScheds as $sc) {
                $emp = $sc->employee;
                if (!$emp) {
                    continue;
                }
                $empType = $emp->employment_type instanceof EmploymentType
                    ? $emp->employment_type
                    : EmploymentType::tryFrom((string) $emp->employment_type);

                if ($empType?->isFreelancer()) {
                    $base = (float) $emp->base_remuneration;
                    $shiftFreelancerCost += ($base > 0 ? $base : 100.00);
                }
            }
        }

        $cmPct = (float) ($dreRef['contribution_margin']['ratio'] ?? 0);
        $cmRatio = $cmPct > 5 ? ($cmPct / 100) : $fallbackCmRatio;
        $shiftBreakEvenTarget = $cmRatio > 0
            ? round(($shiftDailyFixed + $shiftFreelancerCost) / $cmRatio, 2)
            : 0.0;

        $beReached = $shiftBreakEvenTarget > 0 && $shiftRevenue >= $shiftBreakEvenTarget;
        $beDiff = round($shiftRevenue - $shiftBreakEvenTarget, 2);
        $beProgress = $shiftBreakEvenTarget > 0
            ? min(100, round(($shiftRevenue / $shiftBreakEvenTarget) * 100, 1))
            : 0.0;

        // 3. Status de Conciliação (PDV/Gaveta + OFX Banco + CSV Maquininhas)
        $cashBreakage = round((float) ($shift->cash_breakage_amount ?? 0), 2);
        $aposBreakage = round((float) ($shift->apos_breakage_amount ?? 0), 2);
        $hasBreakage = (bool) $shift->has_breakage || abs($cashBreakage) >= 0.05 || abs($aposBreakage) >= 0.05;

        // Cobertura OFX
        $hasOfxForDate = false;
        $pendingOfxInWindow = 0;
        $latestOfxDate = null;
        if (Schema::hasTable('bank_statements')) {
            $latestOfxRaw = BankStatement::max('bank_date');
            $latestOfxDate = $latestOfxRaw ? Carbon::parse($latestOfxRaw)->format('d/m') : null;

            $windowEndStr = $closedAt->copy()->addDay()->toDateString();
            $hasOfxForDate = BankStatement::whereDate('bank_date', '>=', $shiftDateStr)->exists();
            $pendingOfxInWindow = BankStatement::where('status', 'pending')
                ->whereBetween('bank_date', [$shiftDateStr, $windowEndStr])
                ->count();
        }

        // Cobertura Adquirente / Maquininhas
        $hasAcquirerForDate = false;
        $pendingAcquirerInWindow = 0;
        $latestAcquirerDate = null;
        if (Schema::hasTable('acquirer_transactions')) {
            $acqDateCol = Schema::hasColumn('acquirer_transactions', 'transaction_date')
                ? 'transaction_date'
                : (Schema::hasColumn('acquirer_transactions', 'sale_date') ? 'sale_date' : 'created_at');

            $latestAcqRaw = AcquirerTransaction::max($acqDateCol);
            $latestAcquirerDate = $latestAcqRaw ? Carbon::parse($latestAcqRaw)->format('d/m') : null;

            $hasAcquirerForDate = AcquirerTransaction::whereDate($acqDateCol, '>=', $shiftDateStr)->exists();
            $pendingAcquirerInWindow = AcquirerTransaction::whereDate($acqDateCol, $shiftDateStr)
                ->whereIn('status', [
                    AcquirerMatchStatus::ORPHAN->value,
                    AcquirerMatchStatus::AMBIGUOUS->value,
                ])
                ->count();
        }

        // Avaliação consolidada do status
        $isFullyReconciled = !$hasBreakage
            && $hasOfxForDate
            && $hasAcquirerForDate
            && $pendingOfxInWindow === 0
            && $pendingAcquirerInWindow === 0;

        $needsAttention = $hasBreakage || $pendingOfxInWindow > 0 || $pendingAcquirerInWindow > 0;

        return [
            'id'                        => $shift->id,
            'date_label'                => ucfirst($openedAt->locale('pt_BR')->translatedFormat('D, d/m')),
            'period_label'              => $openedAt->format('H:i') . ' às ' . $closedAt->format('H:i'),
            'is_today_shift'            => $shiftDate->isToday(),
            'revenue'                   => $shiftRevenue,
            'orders_count'              => $shiftOrdersCount,
            'break_even_target'         => $shiftBreakEvenTarget,
            'break_even_reached'        => $beReached,
            'break_even_diff'           => $beDiff,
            'break_even_progress'       => $beProgress,
            'has_breakage'              => $hasBreakage,
            'cash_breakage'             => $cashBreakage,
            'apos_breakage'             => $aposBreakage,
            'has_ofx_for_date'          => $hasOfxForDate,
            'pending_ofx_count'         => $pendingOfxInWindow,
            'latest_ofx_date'           => $latestOfxDate,
            'has_acquirer_for_date'     => $hasAcquirerForDate,
            'pending_acquirer_count'    => $pendingAcquirerInWindow,
            'latest_acquirer_date'      => $latestAcquirerDate,
            'is_fully_reconciled'       => $isFullyReconciled,
            'needs_attention'           => $needsAttention,
        ];
    }
}
