<?php

namespace App\Services\Financial;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\BankAccount;
use App\Models\RecurringExpense;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CashFlowProjectionService
{
    protected int $days;
    protected Carbon $startDate;
    protected Carbon $endDate;

    public function __construct(int $days = 30)
    {
        $this->days      = $days;
        $this->startDate = Carbon::today();
        $this->endDate   = Carbon::today()->addDays($days);
    }

    public function project(): array
    {
        // 1. Saldo Inicial Líquido Disponível (Apenas Contas Operacionais: Banco + Cofre, ignorando cartões de crédito)
        $startingBalance = $this->calculateLiquidStartingBalance();

        // 2. Coleta de Recebíveis Futuros por Data (due_date)
        $receivables = AccountReceivable::query()
            ->where('status', 'pending')
            ->whereBetween('due_date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
            ->selectRaw('due_date, SUM(net_amount) as total, COUNT(id) as qty')
            ->groupBy('due_date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->due_date)->toDateString());

        // 3. Coleta de Contas a Pagar Futuras por Data (due_date)
        $payables = AccountPayable::query()
            ->where('status', 'pending')
            ->whereBetween('due_date', [$this->startDate->toDateString(), $this->endDate->toDateString()])
            ->selectRaw('due_date, SUM(amount) as total, COUNT(id) as qty')
            ->groupBy('due_date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->due_date)->toDateString());

        // 4. Despesas Recorrentes Fixas com valor padrão estipulado
        $recurringExpenses = RecurringExpense::query()
            ->where('is_active', true)
            ->where('default_amount', '>', 0)
            ->get();

        // 5. Construção da Linha do Tempo Diária
        $period = CarbonPeriod::create($this->startDate, '1 day', $this->endDate);
        $timeline = [];
        $runningBalance = $startingBalance;
        $totalProjectedInflows  = 0.0;
        $totalProjectedOutflows = 0.0;

        $lowestBalance = [
            'amount' => $startingBalance,
            'date'   => $this->startDate->format('d/m/Y'),
        ];
        $highestBalance = [
            'amount' => $startingBalance,
            'date'   => $this->startDate->format('d/m/Y'),
        ];

        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            $dayOfMonth = (int) $date->format('j');

            // (+) Entradas do Dia
            $dayInflow = 0.0;
            $inflowQty = 0;
            if (isset($receivables[$dateStr])) {
                $dayInflow = (float) ($receivables[$dateStr]->total ?? 0);
                $inflowQty = (int) ($receivables[$dateStr]->qty ?? 0);
            }

            // (-) Saídas Agendadas em Contas a Pagar
            $dayOutflow = 0.0;
            $outflowQty = 0;
            if (isset($payables[$dateStr])) {
                $dayOutflow = (float) ($payables[$dateStr]->total ?? 0);
                $outflowQty = (int) ($payables[$dateStr]->qty ?? 0);
            }

            // (-) Projeção de Recorrentes (se não houver conta a pagar já criada para este mês)
            foreach ($recurringExpenses as $rec) {
                if ((int) $rec->due_day === $dayOfMonth) {
                    $alreadyCreated = AccountPayable::query()
                        ->where('recurring_expense_id', $rec->id)
                        ->whereBetween('due_date', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])
                        ->exists();

                    if (!$alreadyCreated) {
                        $recAmount = (float) ($rec->default_amount ?? 0);
                        $dayOutflow += $recAmount;
                        $outflowQty++;
                    }
                }
            }

            $netDay = $dayInflow - $dayOutflow;
            $runningBalance += $netDay;

            $totalProjectedInflows  += $dayInflow;
            $totalProjectedOutflows += $dayOutflow;

            if ($runningBalance < $lowestBalance['amount']) {
                $lowestBalance = [
                    'amount' => $runningBalance,
                    'date'   => $date->format('d/m/Y'),
                ];
            }

            if ($runningBalance > $highestBalance['amount']) {
                $highestBalance = [
                    'amount' => $runningBalance,
                    'date'   => $date->format('d/m/Y'),
                ];
            }

            $timeline[] = [
                'date'              => $date->format('d/m/Y'),
                'raw_date'          => $dateStr,
                'weekday'           => $date->locale('pt_BR')->translatedFormat('D'),
                'inflow'            => $dayInflow,
                'inflow_qty'        => $inflowQty,
                'outflow'           => $dayOutflow,
                'outflow_qty'       => $outflowQty,
                'net_day'           => $netDay,
                'projected_balance' => $runningBalance,
            ];
        }

        return [
            'meta' => [
                'days'       => $this->days,
                'start_date' => $this->startDate->format('d/m/Y'),
                'end_date'   => $this->endDate->format('d/m/Y'),
            ],
            'summary' => [
                'starting_balance' => $startingBalance,
                'total_inflows'    => $totalProjectedInflows,
                'total_outflows'   => $totalProjectedOutflows,
                'net_projection'   => $totalProjectedInflows - $totalProjectedOutflows,
                'final_balance'    => $runningBalance,
                'lowest_balance'   => $lowestBalance,
                'highest_balance'  => $highestBalance,
                'liquidity_status' => $lowestBalance['amount'] > 10000 ? 'healthy' : ($lowestBalance['amount'] > 0 ? 'warning' : 'critical'),
            ],
            'timeline' => $timeline,
        ];
    }

    protected function calculateLiquidStartingBalance(): float
    {
        // 1. Identifica IDs de contas que são Cartão de Crédito para desconsiderar da disponibilidade imediata
        $creditCardIds = BankAccount::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('type', 'credit_card')
                  ->orWhere('name', 'like', '%Cartão%')
                  ->orWhere('name', 'like', '%Cartao%');
            })
            ->pluck('id')
            ->toArray();

        // 2. Soma todas as entradas e subtrai as saídas das contas bancárias de liquidez imediata
        $query = Transaction::query();
        if (!empty($creditCardIds)) {
            $query->whereNotIn('bank_account_id', $creditCardIds);
        }

        $res = $query->selectRaw("
            SUM(CASE WHEN type = 'in' THEN amount WHEN type = 'out' THEN -amount ELSE 0 END) as balance
        ")->first();

        return (float) ($res->balance ?? 0);
    }
}
