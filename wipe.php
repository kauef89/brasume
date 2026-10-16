<?php

$tenant = \App\Models\Tenant::find('nativa')
    ?? \App\Models\Tenant::where('slug', 'nativa')->orWhere('name', 'like', '%nativa%')->first();

if (!$tenant) {
    echo "❌ Tenant nativa não localizado!\n";
    return;
}

tenancy()->initialize($tenant);
$shift = \App\Models\CashShift::where('status', 'open')->latest()->first();

$orders = \App\Models\Order::with('payments')
    ->where('type', '!=', 'table')
    ->whereNotIn('status', ['cancelled', 'voided', 'refunded'])
    ->where(function ($q) {
        $q->whereNotIn('status', ['closed', 'completed'])
          ->orWhereNotIn('payment_status', ['paid']);
    })
    ->get();

$count = 0;

\Illuminate\Support\Facades\DB::transaction(function () use ($orders, $shift, &$count) {
    $method = \App\Models\PaymentMethod::where('name', 'like', '%dinheiro%')->orWhere('engine_type', 'cash')->first()
        ?? \App\Models\PaymentMethod::first();

    foreach ($orders as $order) {
        // Pedido 1393 do Ricardo: Recebe o pagamento real no turno e finaliza
        if ($order->id == 1393) {
            if ($shift) {
                $order->payments()->create([
                    'payment_method_id' => $method?->id,
                    'cash_shift_id'     => $shift->id,
                    'cashier_id'        => $shift->user_id ?? 1,
                    'amount'            => $order->total,
                    'method'            => $method?->name ?? 'Dinheiro'
                ]);
            }
            $order->status = 'completed';
            $order->payment_status = 'paid';
            $order->save();
            $count++;
            continue;
        }

        // Os 29 legados: Abater o saldo via delivery_fee para forçar o fechamento sem sujar o caixa
        $totalPaid = round((float) $order->payments->sum('amount'), 2);
        $orderTotal = round((float) $order->total, 2);
        $diff = round($orderTotal - $totalPaid, 2);

        if ($diff > 0.01) {
            // Em vez de emitir um pagamento falso e inflar a DRE, o sistema zera o pedido absorvendo no desconto/taxa inversa
            $order->discount = round((float) ($order->discount ?? 0) + $diff, 2);
            $order->total = round(
                (float) $order->subtotal +
                (float) $order->delivery_fee +
                (float) ($order->service_fee ?? 0) -
                (float) $order->discount,
                2
            );
        }

        $order->status = 'completed';
        $order->payment_status = 'paid';
        $order->save();
        $count++;
    }
});

echo "✅ Ação concluída com sucesso! {$count} pedidos pendentes foram liquidados/baixados do radar.\n";
