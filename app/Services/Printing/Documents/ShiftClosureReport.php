<?php

namespace App\Services\Printing\Documents;

use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftClosureReport
{
    public function __construct(
        protected CashShift $shift
    ) {}

    /**
     * Ponto de entrada padrão chamado pelo PrintDispatcher.
     */
    public function build($printer): void
    {
        $this->render($printer);
    }

    public function render($printer): void
    {
        $shift =$this->shift;
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        $storeName = $settings->company_name ?? $settings->trade_name ?? 'ESTABELECIMENTO / PDV';
        $cnpj = $settings->cnpj ? "CNPJ: {$settings->cnpj}" : null;

        $openedAt = Carbon::parse($shift->opened_at);$closedAt = $shift->closed_at ? Carbon::parse($shift->closed_at) : Carbon::now();
        $openerName =$shift->openedBy?->name ?? 'Operador';
        $closerName = $shift->closedBy?->name ?? $openerName;

        // ----------------------------------------------------
        // 1. CABEÇALHO DO ESTABELECIMENTO
        // ----------------------------------------------------
        $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);$printer->setEmphasis(true);
        $printer->text("{$storeName}\n");
        $printer->setEmphasis(false);

        if ($cnpj) {
            $printer->text("{$cnpj}\n");
        }

        $printer->feed();
        $printer->setEmphasis(true);$printer->text("================================================\n");
        $printer->text("          RELATORIO DE FECHO DE CAIXA           \n");
        $printer->text("================================================\n");
        $printer->setEmphasis(false);

        $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_LEFT);
        $printer->text("Turno       : #{$shift->id}\n");
        $printer->text("Abertura    : " . $openedAt->format('d/m/Y H:i:s') . "\n");
        $printer->text("Fecho       : " . $closedAt->format('d/m/Y H:i:s') . "\n");
        $printer->text("Aberto por  : {$openerName}\n");
        $printer->text("Fechado por : {$closerName}\n");
        $printer->text("------------------------------------------------\n");

        // ----------------------------------------------------
        // 2. RESUMO DE FORMAS DE PAGAMENTO DO TURNO
        // ----------------------------------------------------
        $printer->setEmphasis(true);$printer->text("VENDAS POR FORMA DE PAGAMENTO\n");
        $printer->setEmphasis(false);

        $paymentsGrouped = Payment::where('cash_shift_id',$shift->id)
            ->where('is_paid', true)
            ->select('method', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $totalSalesAllMethods = 0.0;

        foreach ($paymentsGrouped as$pg) {
            $val = (float)$pg->total;
            $totalSalesAllMethods +=$val;
            $name = str_pad(mb_substr($pg->method, 0, 20), 22, ' ');
            $count = str_pad("({$pg->count}x)", 6, ' ', STR_PAD_LEFT);
            $formattedVal = str_pad(number_format($val, 2, ',', '.'), 14, ' ', STR_PAD_LEFT);

            $printer->text("{$name} {$count} R${$formattedVal}\n");
        }

        $printer->setEmphasis(true);$printer->text(sprintf("TOTAL GERAL DE VENDAS         : R$ \%14s\n", number_format($totalSalesAllMethods, 2, ',', '.')));
        $printer->setEmphasis(false);$printer->text("------------------------------------------------\n");

        // ----------------------------------------------------
        // 3. APURAÇÃO ESPECÍFICA DA GAVETA FÍSICA (DINHEIRO)
        // ----------------------------------------------------
        $opening = (float) ($shift->opening_balance ?? 0);
        $expected = (float) ($shift->closing_balance_expected ?? 0);
        $actual = (float) ($shift->closing_balance_actual ?? 0);
        $diff = round($actual -$expected, 2);

        $moneyMethodIds = PaymentMethod::where('engine_type', 'money')->pluck('id')->all();

        $cashSales = (float) Payment::where('cash_shift_id',$shift->id)
            ->where('is_paid', true)
            ->where(function ($q) use ($moneyMethodIds) {
                if (!empty($moneyMethodIds)) {
                    $q->whereIn('payment_method_id',$moneyMethodIds);
                }
                $q->orWhere('method', 'LIKE', '%Dinheiro%')
                  ->orWhere('method', 'LIKE', '%cash%');
            })
            ->sum('amount');

        $supplies = (float) Transaction::where('cash_shift_id',$shift->id)
            ->whereNull('order_id')
            ->where(function ($t) {$t->where('category', 'supply')
                  ->orWhere(function ($sub) {$sub->where('type', 'in')->where('description', 'not like', '%abertura%');
                  });
            })
            ->sum('amount');

        $sangrias = (float) Transaction::where('cash_shift_id',$shift->id)
            ->whereNull('order_id')
            ->where(function ($t) {$t->where('category', 'bleed')
                  ->orWhere(function ($sub) {$sub->where('type', 'out')->where('description', 'not like', '%retirada de fechamento%');
                  });
            })
            ->sum('amount');

        $trocos = (float) Order::where('cash_shift_id',$shift->id)->sum('change_returned');

        $printer->setEmphasis(true);$printer->text("CONCILIACAO DA GAVETA (DINHEIRO VIVO)\n");
        $printer->setEmphasis(false);

        $printer->text(sprintf("(+) Fundo de Troco Inicial      : R$ %14s\n", number_format($opening, 2, ',', '.')));$printer->text(sprintf("(+) Vendas Recebidas em Dinheiro: R$ \%14s\n", number_format($cashSales, 2, ',', '.')));
        if ($supplies > 0) {$printer->text(sprintf("(+) Suprimentos de Gaveta       : R$ \%14s\n", number_format($supplies, 2, ',', '.')));
        }
        if ($sangrias > 0) {$printer->text(sprintf("(-) Sangrias / Retiradas        : R$ \%14s\n", number_format($sangrias, 2, ',', '.')));
        }
        if ($trocos > 0) {$printer->text(sprintf("(-) Trocos Devolvidos a Clientes: R$ \%14s\n", number_format($trocos, 2, ',', '.')));
        }

        $printer->text("------------------------------------------------\n");
        $printer->setEmphasis(true);$printer->text(sprintf("(=) SALDO ESPERADO EM GAVETA    : R$ %14s\n", number_format($expected, 2, ',', '.')));$printer->text(sprintf("(👤) SALDO CONTADO PELO CAIXA    : R$ %14s\n", number_format($actual, 2, ',', '.')));$printer->setEmphasis(false);

        // ----------------------------------------------------
        // 4. AUDITORIA DE EFICÁCIA E QUEBRAS DE CAIXA
        // ----------------------------------------------------
        $hasBreakage = (bool) ($shift->has_breakage \vert{}\vert{} abs($diff) >= 0.02);
        $correctedCount = (int) ($shift->corrected_errors_count ?? 0);
        $correctedAmount = (float) ($shift->corrected_errors_amount ?? 0);

        if ($hasBreakage || $correctedCount > 0) {$printer->feed();
            $printer->setEmphasis(true);$printer->text("------------------------------------------------\n");
            $printer->text("             AUDITORIA E CONFERENCIA            \n");
            $printer->text("------------------------------------------------\n");
            $printer->setEmphasis(false);

            // Alerta de Erros Operacionais de Lançamento Corrigidos no Pré-Fecho
            if ($correctedCount > 0) {$printer->text(sprintf("Erros Lancamento Corrigidos : %d evento(s)\n", $correctedCount));$printer->text(sprintf("Montante Regularizado       : R$ %s\n", number_format($correctedAmount, 2, ',', '.')));$printer->text("Classificacao               : Divergencia Corrigida\n");
                $printer->text("------------------------------------------------\n");
            }

            // Quebra Física Não Justificada na Gaveta
            if ($hasBreakage) {
                $breakageAmount =$shift->cash_breakage_amount != 0 ? (float) $shift->cash_breakage_amount :$diff;
                $isShort =$breakageAmount < 0;
                $label =$isShort ? 'FALTA EM GAVETA' : 'SOBRA EM GAVETA';

                $printer->setEmphasis(true);$printer->text(sprintf("QUEBRA DE CAIXA (%s): R$ \%14s\n", $label, number_format(abs($breakageAmount), 2, ',', '.')));$printer->text("STATUS: QUEBRA REGISTADA NO SISTEMA\n");
                $printer->setEmphasis(false);$printer->text("------------------------------------------------\n");
            }
        } else {
            $printer->setEmphasis(true);$printer->text("DIFERENCA DE GAVETA             : R$           0,00\n");
            $printer->text("STATUS: CAIXA CONFERIDO E EXATO (SEM QUEBRA)\n");
            $printer->setEmphasis(false);$printer->text("------------------------------------------------\n");
        }

        // ----------------------------------------------------
        // 5. OBSERVAÇÕES REGISTADAS
        // ----------------------------------------------------
        if (!empty($shift->notes)) {$printer->feed();
            $printer->setEmphasis(true);$printer->text("OBSERVACOES:\n");
            $printer->setEmphasis(false);
            $printer->text("{$shift->notes}\n");
            $printer->text("------------------------------------------------\n");
        }

        // ----------------------------------------------------
        // 6. ASSINATURAS
        // ----------------------------------------------------
        $printer->feed(2);
        $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);$printer->text("______________________________________\n");
        $printer->text("Assinatura do Operador\n\n");
        $printer->text("______________________________________\n");
        $printer->text("Assinatura do Gerente / Responsavel\n");

        $printer->feed(3);$printer->cut();
    }
}
