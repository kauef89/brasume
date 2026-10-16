<?php

$tenant = \App\Models\Tenant::find('nativa')
    ?? \App\Models\Tenant::where('slug', 'nativa')->orWhere('name', 'like', '%nativa%')->first();

if (!$tenant) {
    echo "❌ Tenant nativa não localizado!\n";
    return;
}

tenancy()->initialize($tenant);

$shift = \App\Models\CashShift::where('status', 'open')->latest()->first();

echo "\n==================== TURNO ATUAL ====================\n";
if ($shift) {
    echo "Turno ID: {$shift->id}\n";
    echo "Abertura: {$shift->opened_at}\n";
    echo "Status: {$shift->status}\n";
} else {
    echo "⚠️ Nenhum turno com status 'open' encontrado!\n";
}
echo "=====================================================\n\n";

// Busca os pedidos não-mesa (balcão, delivery, retirada) que impedem o fechamento:
// 1. Status diferente de finalizado/cancelado OU
// 2. Pagamento pendente/parcial
$blockingOrders = \App\Models\Order::query()
    ->where('type', '!=', 'table')
    ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
    ->where(function ($q) {
        $q->whereNotIn('status', ['closed', 'completed'])
          ->orWhereNotIn('payment_status', ['paid']);
    })
    ->with(['customer', 'payments'])
    ->get();

echo "🔍 Total de pedidos balcão/delivery pendentes: " . $blockingOrders->count() . "\n\n";

if ($blockingOrders->isEmpty()) {
    echo "Nenhum pedido encontrado na regra padrão. Buscando em modo amplo (todos não finalizados):\n";
    $broadSearch = \App\Models\Order::whereNotIn('status', ['closed', 'completed', 'cancelled'])
        ->get(['id', 'type', 'status', 'payment_status', 'created_at']);
    echo json_encode($broadSearch, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    return;
}

foreach ($blockingOrders as $order) {
    $paidSum = (float) $order->payments->sum('amount');
    $balance = round((float) $order->total - $paidSum, 2);

    echo "--------------------------------------------------\n";
    echo "📦 PEDIDO #{$order->id}\n";
    echo "Tipo: {$order->type}\n";
    echo "Origem/Canal: " . ($order->source ?? 'N/A') . "\n";
    echo "Status do Pedido: {$order->status}\n";
    echo "Status de Pagamento: {$order->payment_status}\n";
    echo "Valor Total: R$ " . number_format($order->total, 2, ',', '.') . "\n";
    echo "Total Pago: R$ " . number_format($paidSum, 2, ',', '.') . "\n";
    echo "Saldo Devedor: R$ " . number_format($balance, 2, ',', '.') . "\n";
    echo "Cliente: " . ($order->customer_name ?? $order->customer?->name ?? 'Consumidor Final') . "\n";
    echo "Criado em: " . $order->created_at?->format('d/m/Y H:i:s') . "\n";
    echo "--------------------------------------------------\n";
}
