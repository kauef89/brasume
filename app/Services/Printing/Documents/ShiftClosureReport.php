<?php

namespace App\Services\Printing\Documents;

use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ShiftClosureReport
{
    protected array $blueprint = [];
    protected CashShift $shift;

    public function __construct(CashShift $shift)
    {
        $this->shift = $shift->fresh();
    }

    public function getJobType(): string
    {
        return 'shift_closure_report';
    }

    public function getOrderId(): ?int
    {
        return null;
    }

    public function build(): array
    {
        $this->buildHeader();
        $this->buildLifecycleSection();
        $this->buildDrawerSection();
        $this->buildPaymentsSection();
        $this->buildLossSection();
        $this->buildChannelBreakdownSection();
        $this->buildGrandTotalSection();
        $this->buildFooter();

        $this->blueprint[] = ['cmd' => 'beep'];
        $this->blueprint[] = ['cmd' => 'cut'];

        return $this->blueprint;
    }

    private function buildHeader(): void
    {
        $settings = CompanySetting::getCached();
        $settingsArray = $settings?->toArray() ?? [];
        $companyName = $settings->trade_name ?? $settings->company_name ?? $settings->name ?? 'RESTAURANTE';

        // 🎯 LOGOTIPO DINÂMICO DO TENANT (OU NOME FANTASIA)
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];

        $tenant = function_exists('tenant') ? tenant() : null;
        $domain = $tenant?->domains?->first()?->domain
            ?? ($tenant ? $tenant->id . '.brasume.com.br' : request()->getHost());

        $logoUrl = !empty($settingsArray['receipt_logo_url'])
            ? $settingsArray['receipt_logo_url']
            : (!empty($settingsArray['logo_path']) ? "https://{$domain}/media/file?path=" . $settingsArray['logo_path'] : null);

        if ($logoUrl) {
            $this->blueprint[] = ['cmd' => 'image', 'val' => $logoUrl];
        } else {
            $this->blueprint[] = ['cmd' => 'text', 'val' => Str::ascii(mb_strtoupper($companyName)), 'bold' => true, 'size' => '2x'];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => "FECHAMENTO DE TURNO #{$this->shift->id}", 'bold' => true, 'size' => '1x'];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
    }

    private function buildLifecycleSection(): void
    {
        $openedAt = $this->shift->opened_at ? Carbon::parse($this->shift->opened_at)->format('d/m/Y H:i') : '--';
        $closedAt = $this->shift->closed_at ? Carbon::parse($this->shift->closed_at)->format('d/m/Y H:i') : now()->format('d/m/Y H:i');

        $openedBy = null;
        if ($this->shift->opened_by) {
            $openedBy = User::find($this->shift->opened_by)?->name;
        }

        $closedBy = null;
        if ($this->shift->closed_by) {
            $closedBy = User::find($this->shift->closed_by)?->name;
        }

        $openedBy = $openedBy ?: 'Sistema';
        $closedBy = $closedBy ?: (auth()->user()->name ?? 'Sistema');

        $duration = '--';
        if ($this->shift->opened_at) {
            $start = Carbon::parse($this->shift->opened_at);
            $end = $this->shift->closed_at ? Carbon::parse($this->shift->closed_at) : now();
            $duration = $start->diff($end)->format('%Hh %Im');
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->row('Abertura:', "{$openedAt} ({$openedBy})"), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->row('Fechamento:', "{$closedAt} ({$closedBy})"), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->row('Duracao:', $duration), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildDrawerSection(): void
    {
        $openedAt = $this->shift->opened_at;
        $closedAt = $this->shift->closed_at ?? now();

        $moneyMethodIds = PaymentMethod::where('engine_type', 'money')->pluck('id')->all();

        $cashSales = (float) Payment::where('is_paid', true)
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->whereHas('order', fn($q) => $q->whereIn('status', ['closed', 'completed', 'dispatched', 'preparing']))
            ->where(function ($q) use ($moneyMethodIds) {
                if (!empty($moneyMethodIds)) {
                    $q->whereIn('payment_method_id', $moneyMethodIds);
                }
                $q->orWhere('method', 'LIKE', '%Dinheiro%');
            })
            ->sum('amount');

        $shiftTransactions = Transaction::where('cash_shift_id', $this->shift->id)
            ->whereNull('order_id')
            ->get();

        if ($shiftTransactions->isEmpty()) {
            $shiftTransactions = Transaction::whereBetween('transaction_date', [$openedAt, $closedAt])
                ->where('bank_account_id', $this->shift->bank_account_id)
                ->whereNull('order_id')
                ->get();
        }

        $supplies = (float) $shiftTransactions->filter(function ($t) {
            return $t->category === 'supply'
                || ($t->type === 'in' && !str_contains(mb_strtolower($t->description), 'abertura'));
        })->sum('amount');

        $sangrias = (float) $shiftTransactions->filter(function ($t) {
            return $t->category === 'bleed'
                || ($t->type === 'out' && !str_contains(mb_strtolower($t->description), 'retirada de fechamento'));
        })->sum('amount');

        $opening = (float) $this->shift->opening_balance;
        $expected = (float) ($this->shift->closing_balance_expected ?? ($opening + $cashSales + $supplies - $sangrias));
        $actual = (float) ($this->shift->closing_balance_actual ?? $expected);
        $diff = round($actual - $expected, 2);

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'CONCILIACAO DA GAVETA (DINHEIRO)', 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('Fundo de Abertura:', $opening), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(+) Vendas em Dinheiro:', $cashSales), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(+) Suprimentos (Reforco):', $supplies), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(-) Sangrias (Retiradas):', $sangrias), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];

        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(=) Saldo Esperado:', $expected), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(=) Saldo Informado (Contado):', $actual), 'bold' => true];

        $diffTag = ($diff == 0) ? 'EXATO' : (($diff > 0) ? 'SOBRA' : 'FALTA');
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->diffRow('Diferenca de Caixa:', $diffTag, $diff), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildPaymentsSection(): void
    {
        $openedAt = $this->shift->opened_at;
        $closedAt = $this->shift->closed_at ?? now();

        $payments = Payment::where('is_paid', true)
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->whereHas('order', fn($q) => $q->whereIn('status', ['closed', 'completed', 'dispatched', 'preparing']))
            ->get();

        $revenuePayments = $payments->reject(fn($p) => $p->paymentMethod?->engine_type === 'loss');
        $grouped = $revenuePayments->groupBy(fn($p) => $p->method ?: 'Outros');

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'FATURAMENTO POR FORMA DE PAGAMENTO', 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->headerRow3('FORMA', 'QTD', 'TOTAL'), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];

        foreach ($grouped as $methodName => $methodPayments) {
            $sum = (float) $methodPayments->sum('amount');
            $count = $methodPayments->count() . 'x';
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow3($methodName, $count, $sum), 'bold' => false];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildChannelBreakdownSection(): void
    {
        $openedAt = $this->shift->opened_at;
        $closedAt = $this->shift->closed_at ?? now();

        $orders = Order::where(function ($q) use ($openedAt, $closedAt) {
            $q->whereBetween('created_at', [$openedAt, $closedAt])
              ->orWhereBetween('updated_at', [$openedAt, $closedAt]);
        })->get();

        $tableOrders = $orders->where('type', 'table')->where('status', '!=', 'cancelled');
        $counterOrders = $orders->where('type', 'counter')->where('status', '!=', 'cancelled');
        $deliveryOrders = $orders->where('type', 'delivery')->where('status', '!=', 'cancelled');
        $cancelledOrders = $orders->where('status', 'cancelled');

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'RESUMO OPERACIONAL POR CANAL', 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->headerRow3('CANAL', 'PEDIDOS', 'TOTAL'), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];

        $this->blueprint[] = [
            'cmd' => 'text',
            'val' => $this->moneyRow3('Mesa', $tableOrders->count() . ' ped', (float) $tableOrders->sum('total')),
            'bold' => false
        ];
        $this->blueprint[] = [
            'cmd' => 'text',
            'val' => $this->moneyRow3('Balcao', $counterOrders->count() . ' ped', (float) $counterOrders->sum('total')),
            'bold' => false
        ];
        $this->blueprint[] = [
            'cmd' => 'text',
            'val' => $this->moneyRow3('Delivery', $deliveryOrders->count() . ' ped', (float) $deliveryOrders->sum('total')),
            'bold' => false
        ];

        if ($cancelledOrders->isNotEmpty()) {
            $this->blueprint[] = [
                'cmd' => 'text',
                'val' => $this->moneyRow3('Cancelados', $cancelledOrders->count() . ' ped', (float) $cancelledOrders->sum('total')),
                'bold' => false
            ];
        }

        $totalOrdersCount = $tableOrders->count() + $counterOrders->count() + $deliveryOrders->count();
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];
        $this->blueprint[] = [
            'cmd' => 'text',
            'val' => $this->row('TOTAL DE PEDIDOS ATENDIDOS:', "{$totalOrdersCount} ped"),
            'bold' => true
        ];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    
    private function buildLossSection(): void
    {
        $openedAt = $this->shift->opened_at;
        $closedAt = $this->shift->closed_at ?? now();

        $lossPayments = Payment::with('paymentMethod')
            ->where('is_paid', true)
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->whereHas('order', fn($q) => $q->whereIn('status', ['closed', 'completed', 'dispatched', 'preparing']))
            ->whereHas('paymentMethod', fn($q) => $q->where('engine_type', 'loss'))
            ->get();

        if ($lossPayments->isEmpty()) {
            return;
        }

        $grouped = $lossPayments->groupBy(fn($p) => $p->method ?: 'Perda / Cortesia');

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'BAIXAS OPERACIONAIS / PERDAS (NAO FATURADO)', 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->headerRow3('MOTIVO / FORMA', 'QTD', 'TOTAL'), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];

        foreach ($grouped as $methodName => $methodPayments) {
            $sum = (float) $methodPayments->sum('amount');
            $count = $methodPayments->count() . 'x';
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow3($methodName, $count, $sum), 'bold' => false];
        }

        $totalLoss = (float) $lossPayments->sum('amount');
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];
        $this->blueprint[] = [
            'cmd' => 'text',
            'val' => $this->moneyRow('TOTAL PERDAS / CORTESIAS:', $totalLoss),
            'bold' => true
        ];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildGrandTotalSection(): void
    {
        $openedAt = $this->shift->opened_at;
        $closedAt = $this->shift->closed_at ?? now();

        $totalSales = Payment::where('is_paid', true)
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->whereHas('order', fn($q) => $q->whereIn('status', ['closed', 'completed', 'dispatched', 'preparing']))
            ->whereDoesntHave('paymentMethod', fn($q) => $q->where('engine_type', 'loss'))
            ->sum('amount');

        $this->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];
        $this->blueprint[] = ['cmd' => 'text', 'val' => 'TOTAL FATURADO NO TURNO', 'bold' => true, 'size' => '1x'];
        $this->blueprint[] = [
            'cmd'  => 'text',
            'val'  => 'R$ ' . number_format($totalSales, 2, ',', '.'),
            'bold' => true,
            'size' => '2x'
        ];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
    }

    private function buildFooter(): void
    {
        if (!empty($this->shift->notes)) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => 'OBSERVACOES:', 'bold' => true];
            $this->blueprint[] = ['cmd' => 'text', 'val' => Str::ascii($this->shift->notes), 'bold' => false];
            $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
        }

        $now = now()->format('d/m/Y H:i:s');
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];
        $this->blueprint[] = ['cmd' => 'text', 'val' => "EMISSAO: {$now}", 'bold' => false, 'size' => '1x'];
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
    }

    private function row(string $left, string $right, int $width = 48): string
    {
        $left = Str::ascii($left);
        $right = Str::ascii($right);

        $spaces = max(1, $width - mb_strlen($left) - mb_strlen($right));
        return $left . str_repeat(' ', $spaces) . $right;
    }

    private function moneyRow(string $label, float $amount, int $width = 48): string
    {
        $label = Str::ascii($label);
        $val = number_format($amount, 2, ',', '.');
        $labelCol = Str::padRight(mb_substr($label, 0, 35), 35);
        return $labelCol . 'R$ ' . Str::padLeft($val, 10);
    }

    private function diffRow(string $label, string $tag, float $diff, int $width = 48): string
    {
        $label = Str::ascii($label);
        $val = number_format(abs($diff), 2, ',', '.');
        $sign = ($diff > 0) ? '+ ' : (($diff < 0) ? '- ' : '');
        $colVal = $sign . $val;

        $col1 = Str::padRight(mb_substr($label, 0, 25), 25);
        $col2 = Str::padBoth(mb_substr("({$tag})", 0, 10), 10);
        return $col1 . $col2 . 'R$ ' . Str::padLeft($colVal, 10);
    }

    private function moneyRow3(string $left, string $mid, float $amount, int $width = 48): string
    {
        $left = Str::ascii($left);
        $mid = Str::ascii($mid);
        $val = number_format($amount, 2, ',', '.');

        $col1 = Str::padRight(mb_substr($left, 0, 25), 25);
        $col2 = Str::padBoth(mb_substr($mid, 0, 10), 10);
        return $col1 . $col2 . 'R$ ' . Str::padLeft($val, 10);
    }

    private function headerRow3(string $left, string $mid, string $right, int $width = 48): string
    {
        $left = Str::ascii($left);
        $mid = Str::ascii($mid);
        $right = Str::ascii($right);

        $col1 = Str::padRight(mb_substr($left, 0, 25), 25);
        $col2 = Str::padBoth(mb_substr($mid, 0, 10), 10);
        $col3 = Str::padLeft(mb_substr($right, 0, 13), 13);
        return $col1 . $col2 . $col3;
    }
}
