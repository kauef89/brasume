<?php

use App\Models\CashShift;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Order;
use App\Models\ShiftAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

tenancy()->initialize("nativa");

// Localiza o turno de terça-feira (03/11/2026) ou o mais recente fechado
$shift = CashShift::whereDate("opened_at", "2026-11-03")
    ->where("status", "closed")
    ->latest("id")
    ->first() 
    ?? CashShift::where("status", "closed")->latest("id")->first();

if (!$shift) {
    echo "❌ Nenhum turno fechado encontrado.\n";
    return;
}

echo "\n==========================================================\n";
echo "🔍 DIAGNÓSTICO DO TURNO #{$shift->id}\n";
echo "Abertura  : " . Carbon::parse($shift->opened_at)->format("d/m/Y H:i:s") . "\n";
echo "Fechamento: " . ($shift->closed_at ? Carbon::parse($shift->closed_at)->format("d/m/Y H:i:s") : "Em aberto") . "\n";
echo "Notas     : " . ($shift->notes ?? "Nenhuma") . "\n";
echo "==========================================================\n\n";

$abertura   = (float) $shift->opening_balance;
$esperado   = (float) $shift->closing_balance_expected;
$apurado    = (float) $shift->closing_balance_actual;
$diferenca  = round($apurado - $esperado, 2);

echo "💰 SALDOS REGISTRADOS NO TURNO:\n";
echo "   (+) Fundo de Abertura : R$ " . number_format($abertura, 2, ",", ".") . "\n";
echo "   (=) Saldo Esperado    : R$ " . number_format($esperado, 2, ",", ".") . "\n";
echo "   (👤) Saldo Contado     : R$ " . number_format($apurado, 2, ",", ".") . "\n";
echo "   (⚠️) Diferença Apurada: R$ " . number_format($diferenca, 2, ",", ".") . "\n\n";

// Pagamentos em dinheiro
$cashPayments = Payment::where("cash_shift_id", $shift->id)
    ->where(function($q) {
        $q->where("method", "cash")
          ->orWhere("method", "dinheiro");
    })
    ->get();
$totalVendasDinheiro = (float) $cashPayments->sum("amount");

// Suprimentos
$suprimentos = Transaction::where("cash_shift_id", $shift->id)
    ->where("type", "in")
    ->get();
$totalSuprimentos = (float) $suprimentos->sum("amount");

// Sangrias / Saídas de gaveta
$sangrias = Transaction::where("cash_shift_id", $shift->id)
    ->where("type", "out")
    ->get();
$totalSangrias = (float) $sangrias->sum("amount");

// Trocos devolvidos em pedidos
$trocosDevolvidos = (float) Order::where("cash_shift_id", $shift->id)->sum("change_returned");

$calcSemTroco = $abertura + $totalVendasDinheiro + $totalSuprimentos - $totalSangrias;
$calcComTroco = $calcSemTroco - $trocosDevolvidos;

echo "🧮 RECONSTRUÇÃO DA GAVETA (DINHEIRO):\n";
echo "   (+) Fundo Inicial               : R$ " . number_format($abertura, 2, ",", ".") . "\n";
echo "   (+) Vendas Dinheiro ({$cashPayments->count()}x)      : R$ " . number_format($totalVendasDinheiro, 2, ",", ".") . "\n";
echo "   (+) Suprimentos ({$suprimentos->count()}x)            : R$ " . number_format($totalSuprimentos, 2, ",", ".") . "\n";
echo "   (-) Sangrias ({$sangrias->count()}x)                : R$ " . number_format($totalSangrias, 2, ",", ".") . "\n";
echo "   (-) Trocos Devolvidos           : R$ " . number_format($trocosDevolvidos, 2, ",", ".") . "\n";
echo "   ------------------------------------------------------\n";
echo "   (=) Calculado (sem subtrair troco): R$ " . number_format($calcSemTroco, 2, ",", ".") . "\n";
echo "   (=) Calculado (subtraindo troco)  : R$ " . number_format($calcComTroco, 2, ",", ".") . "\n";
echo "   (=) Saldo Esperado do Sistema     : R$ " . number_format($esperado, 2, ",", ".") . "\n\n";

echo "🔻 SANGRIAS E SAÍDAS REGISTRADAS:\n";
if ($sangrias->isEmpty()) {
    echo "   (Nenhuma sangria registrada)\n";
} else {
    foreach ($sangrias as $sg) {
        echo "   • R$ " . number_format($sg->amount, 2, ",", ".") . " | Cat: {$sg->category} | Desc: {$sg->description}\n";
    }
}
echo "\n";

echo "👥 PRESENÇAS / DIÁRIAS DO TURNO:\n";
$attendances = ShiftAttendance::with("user")->where("cash_shift_id", $shift->id)->get();
foreach ($attendances as $att) {
    $uName = $att->user?->name ?? "User #{$att->user_id}";
    $pago = $att->account_payable_id ? "PAGO (Payable #{$att->account_payable_id})" : "NÃO PAGO";
    echo "   • {$uName} | Cargo: {$att->assigned_role} | Diária: R$ " . number_format($att->daily_rate, 2, ",", ".") . " | Status: {$pago}\n";
}
echo "\n";

echo "🎯 INVESTIGAÇÃO DO VALOR ALVO (R$ 67,50):\n";

// 1. Pedidos com valor total ou subtotal próximo
$pedidos = Order::where("cash_shift_id", $shift->id)
    ->where(function($q) {
        $q->whereBetween("total", [67.00, 68.00])
          ->orWhereBetween("subtotal", [67.00, 68.00]);
    })->get();
foreach ($pedidos as $po) {
    echo "   👉 [PEDIDO] #{$po->id} | Total: R$ {$po->total} | Subtotal: R$ {$po->subtotal} | Tipo: {$po->type} | Status: {$po->status} | Pagamento: {$po->payment_status}\n";
}

// 2. Pagamentos individuais no valor
$pagamentos = Payment::where("cash_shift_id", $shift->id)
    ->whereBetween("amount", [67.00, 68.00])
    ->get();
foreach ($pagamentos as $pa) {
    echo "   👉 [PAGAMENTO] #{$pa->id} (Pedido #{$pa->order_id}) | R$ {$pa->amount} | Método: {$pa->method} | Status: {$pa->status}\n";
}

// 3. Transações de caixa no valor
$transacoes = Transaction::where("cash_shift_id", $shift->id)
    ->whereBetween("amount", [67.00, 68.00])
    ->get();
foreach ($transacoes as $ta) {
    echo "   👉 [TRANSAÇÃO] #{$ta->id} | R$ {$ta->amount} | Tipo: {$ta->type} | Desc: {$ta->description}\n";
}

// 4. Itens de pedidos que totalizam o valor
$itemsAlvo = DB::table("order_items")
    ->join("orders", "order_items.order_id", "=", "orders.id")
    ->where("orders.cash_shift_id", $shift->id)
    ->whereBetween("order_items.total_price", [67.00, 68.00])
    ->select("order_items.*")
    ->get();
foreach ($itemsAlvo as $ia) {
    echo "   👉 [ITEM DO PEDIDO] Pedido #{$ia->order_id} | Produto: {$ia->product_name} | Qtd: {$ia->quantity} | Total: R$ {$ia->total_price}\n";
}

echo "\n💳 RESUMO DE MÉTODOS DE PAGAMENTO DO TURNO:\n";
$resumo = Payment::where("cash_shift_id", $shift->id)
    ->select("method", DB::raw("COUNT(*) as qtd"), DB::raw("SUM(amount) as total"))
    ->groupBy("method")
    ->get();
foreach ($resumo as $rm) {
    echo "   • " . str_pad($rm->method, 15) . ": {$rm->qtd}x | Total: R$ " . number_format($rm->total, 2, ",", ".") . "\n";
}
echo "\n==========================================================\n";
