<?php

namespace App\Services\Printing\Documents;

use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
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

    public function getTitle(): string
    {
        return "Fechamento de Turno #{$this->shift->id}";
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
        $this->buildTerminalSettlementsSection();
        $this->buildPaymentsSection();
        $this->buildAuditSection();
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
        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        $companyName = $settings->trade_name ?? $settings->company_name ?? 'ESTABELECIMENTO';
        $cnpj = $settings->cnpj ? "CNPJ: {$settings->cnpj}" : null;

        $this->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];

        $tenant = function_exists('tenant') ? tenant() : null;
        $domain = $tenant?->domains?->first()?->domain
            ?? ($tenant ? $tenant->id . '.brasume.com.br' : request()->getHost());

        $logoUrl = !empty($settings->receipt_logo_url)
            ? $settings->receipt_logo_url
            : (!empty($settings->logo_path) ? "https://{$domain}/media/file?path=" . $settings->logo_path : null);

        if ($logoUrl) {
            $this->blueprint[] = ['cmd' => 'image', 'val' => $logoUrl];
        } else {
            $this->blueprint[] = ['cmd' => 'text', 'val' => Str::ascii(mb_strtoupper($companyName)), 'bold' => true, 'size' => '2x'];
        }

        if ($cnpj) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => $cnpj, 'bold' => false];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => "FECHAMENTO DE TURNO #{$this->shift->id}", 'bold' => true, 'size' => '1x'];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
    }

    private function buildLifecycleSection(): void
    {
        $openedAt = $this->shift->opened_at ? Carbon::parse($this->shift->opened_at)->format('d/m/Y H:i') : '--';
        $closedAt = $this->shift->closed_at ? Carbon::parse($this->shift->closed_at)->format('d/m/Y H:i') : now()->format('d/m/Y H:i');

        $openedBy = $this->shift->opened_by ? User::find($this->shift->opened_by)?->name : 'Sistema';
        $closedBy = $this->shift->closed_by ? User::find($this->shift->closed_by)?->name : (auth()->user()?->name ?? 'Sistema');

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
        $opening = (float) ($this->shift->opening_balance ?? 0);
        $expected = (float) ($this->shift->closing_balance_expected ?? 0);
        $actual = (float) ($this->shift->closing_balance_actual ?? 0);
        $diff = round($actual - $expected, 2);

        $moneyMethodIds = PaymentMethod::where('engine_type', 'money')->pluck('id')->all();

        $cashSales = (float) Payment::where('cash_shift_id', $this->shift->id)
            ->where('is_paid', true)
            ->where(function ($q) use ($moneyMethodIds) {
                if (!empty($moneyMethodIds)) {
                    $q->whereIn('payment_method_id', $moneyMethodIds);
                }
                $q->orWhere('method', 'LIKE', '%Dinheiro%')
                  ->orWhere('method', 'LIKE', '%cash%');
            })
            ->sum('amount');

        $supplies = (float) Transaction::where('cash_shift_id', $this->shift->id)
            ->whereNull('order_id')
            ->where(function ($t) {
                $t->where('category', 'supply')
                  ->orWhere(function ($sub) {
                      $sub->where('type', 'in')->where('description', 'not like', '%abertura%');
                  });
            })
            ->sum('amount');

        $sangrias = (float) Transaction::where('cash_shift_id', $this->shift->id)
            ->whereNull('order_id')
            ->where(function ($t) {
                $t->where('category', 'bleed')
                  ->orWhere(function ($sub) {
                      $sub->where('type', 'out')->where('description', 'not like', '%retirada de fechamento%');
                  });
            })
            ->sum('amount');

        $trocos = (float) Order::where('cash_shift_id', $this->shift->id)->sum('change_returned');

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'CONCILIACAO DA GAVETA (DINHEIRO)', 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('Fundo de Abertura:', $opening), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(+) Vendas em Dinheiro:', $cashSales), 'bold' => false];
        if ($supplies > 0) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(+) Suprimentos (Reforco):', $supplies), 'bold' => false];
        }
        if ($sangrias > 0) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(-) Sangrias (Retiradas):', $sangrias), 'bold' => false];
        }
        if ($trocos > 0) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(-) Trocos Devolvidos:', $trocos), 'bold' => false];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(=) Saldo Esperado:', $expected), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('(=) Saldo Informado (Contado):', $actual), 'bold' => true];

        $diffTag = ($diff == 0) ? 'EXATO' : (($diff > 0) ? 'SOBRA' : 'FALTA');
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->diffRow('Diferenca de Gaveta:', $diffTag, $diff), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildTerminalSettlementsSection(): void
    {
        $settlements = $this->shift->terminal_settlements;
        if (empty($settlements) || !is_array($settlements)) {
            return;
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'CONFERENCIA DE MAQUININHAS (APOS / SITEF)', 'bold' => true];

        $totalDeclared = 0.0;

        foreach ($settlements as $tid => $term) {
            $name = $term['name'] ?? "Terminal {$tid}";
            $cards = (float) ($term['cards'] ?? 0);
            $pix = (float) ($term['pix'] ?? 0);
            $voucher = (float) ($term['voucher'] ?? 0);
            $subtotal = (float) ($term['total'] ?? ($cards + $pix + $voucher));
            $totalDeclared += $subtotal;

            $this->blueprint[] = ['cmd' => 'text', 'val' => "• {$name} (TID: {$tid})", 'bold' => true];
            if ($cards > 0) {
                $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('   Bandeiras (Deb/Cred):', $cards), 'bold' => false];
            }
            if ($pix > 0) {
                $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('   Pix Compra (Maq):', $pix), 'bold' => false];
            }
            if ($voucher > 0) {
                $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('   Voucher (VR/VA):', $voucher), 'bold' => false];
            }
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('   Subtotal Terminal:', $subtotal), 'bold' => false];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('TOTAL DECLARADO NAS FITAS:', $totalDeclared), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildPaymentsSection(): void
    {
        $payments = Payment::where('cash_shift_id', $this->shift->id)
            ->where('is_paid', true)
            ->get();

        $revenuePayments = $payments->reject(fn($p) => ($p->paymentMethod?->engine_type === \App\Enums\PaymentEngineType::LOSS || $p->paymentMethod?->engine_type?->value === 'loss'));
        $grouped = $revenuePayments->groupBy(fn($p) => $p->method ?: 'Outros');

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'VENDAS POR FORMA DE PAGAMENTO', 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->headerRow3('FORMA', 'QTD', 'TOTAL'), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];

        foreach ($grouped as $methodName => $methodPayments) {
            $sum = (float) $methodPayments->sum('amount');
            $count = $methodPayments->count() . 'x';
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow3($methodName, $count, $sum), 'bold' => false];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildAuditSection(): void
    {
        $diff = round((float) ($this->shift->closing_balance_actual - $this->shift->closing_balance_expected), 2);
        $hasBreakage = (bool) ($this->shift->has_breakage || abs($diff) >= 0.02);
        $correctedCount = (int) ($this->shift->corrected_errors_count ?? 0);
        $correctedAmount = (float) ($this->shift->corrected_errors_amount ?? 0);

        if (!$hasBreakage && $correctedCount === 0) {
            return;
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'AUDITORIA E CONFERENCIA DO TURNO', 'bold' => true];

        if ($correctedCount > 0) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->row('Erros Corrigidos no Pre-Fecho:', "{$correctedCount} evento(s)"), 'bold' => false];
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow('Montante Regularizado:', $correctedAmount), 'bold' => false];
            $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];
        }

        if ($hasBreakage) {
            $breakage = $this->shift->cash_breakage_amount != 0 ? (float) $this->shift->cash_breakage_amount : $diff;
            $isShort = $breakage < 0;
            $label = $isShort ? 'FALTA EM GAVETA' : 'SOBRA EM GAVETA';
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->diffRow('Quebra Registrada:', $label, $breakage), 'bold' => true];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildChannelBreakdownSection(): void
    {
        $orders = Order::where('cash_shift_id', $this->shift->id)->get();

        if ($orders->isEmpty()) {
            return;
        }

        $tableOrders = $orders->where('type', 'table')->where('status', '!=', 'cancelled');
        $counterOrders = $orders->where('type', 'counter')->where('status', '!=', 'cancelled');
        $deliveryOrders = $orders->where('type', 'delivery')->where('status', '!=', 'cancelled');
        $cancelledOrders = $orders->where('status', 'cancelled');

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'RESUMO OPERACIONAL POR CANAL', 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->headerRow3('CANAL', 'PEDIDOS', 'TOTAL'), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];

        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow3('Mesa', $tableOrders->count() . ' ped', (float) $tableOrders->sum('total')), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow3('Balcao', $counterOrders->count() . ' ped', (float) $counterOrders->sum('total')), 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow3('Delivery', $deliveryOrders->count() . ' ped', (float) $deliveryOrders->sum('total')), 'bold' => false];

        if ($cancelledOrders->isNotEmpty()) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow3('Cancelados', $cancelledOrders->count() . ' ped', (float) $cancelledOrders->sum('total')), 'bold' => false];
        }

        $totalCount = $tableOrders->count() + $counterOrders->count() + $deliveryOrders->count();
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->row('TOTAL DE PEDIDOS:', "{$totalCount} ped"), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildLossSection(): void
    {
        $lossPayments = Payment::where('cash_shift_id', $this->shift->id)
            ->where('is_paid', true)
            ->whereHas('paymentMethod', fn($q) => $q->where('engine_type', 'loss'))
            ->get();

        if ($lossPayments->isEmpty()) {
            return;
        }

        $grouped = $lossPayments->groupBy(fn($p) => $p->method ?: 'Perda / Cortesia');

        $this->blueprint[] = ['cmd' => 'text', 'val' => 'BAIXAS OPERACIONAIS / PERDAS', 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->headerRow3('MOTIVO', 'QTD', 'TOTAL'), 'bold' => true];
        $this->blueprint[] = ['cmd' => 'text', 'val' => ' . . . . . . . . . . . . . . . . . . . . . . . .', 'bold' => false];

        foreach ($grouped as $name => $items) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->moneyRow3($name, $items->count() . 'x', (float) $items->sum('amount')), 'bold' => false];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
    }

    private function buildGrandTotalSection(): void
    {
        $totalSales = (float) Payment::where('cash_shift_id', $this->shift->id)
            ->where('is_paid', true)
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
        $this->blueprint[] = ['cmd' => 'text', 'val' => "EMISSAO: {$now}", 'bold' => false];

        $this->blueprint[] = ['cmd' => 'feed', 'val' => 2];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '______________________________________', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => 'Assinatura do Operador', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'feed', 'val' => 1];
        $this->blueprint[] = ['cmd' => 'text', 'val' => '______________________________________', 'bold' => false];
        $this->blueprint[] = ['cmd' => 'text', 'val' => 'Assinatura do Gerente / Responsavel', 'bold' => false];
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
