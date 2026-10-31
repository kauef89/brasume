<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\Orders\OrderLogisticsService;
use Illuminate\Http\Request;

class OrderLogisticsController extends Controller
{
    public function __construct(protected OrderLogisticsService $logisticsService) {}

    public function transform(Order $order, Request $request)
    {
        $validated = $request->validate([
            'new_type'        => 'nullable|string|in:table,counter,delivery',
            'newType'         => 'nullable|string|in:table,counter,delivery',
            'type'            => 'nullable|string|in:table,counter,delivery',
            'table_id'        => 'nullable|integer|exists:dining_tables,id',
            'dining_table_id' => 'nullable|integer|exists:dining_tables,id',
            'table_number'    => 'nullable|string',
            'identifier'      => 'nullable|string',
            'customer_name'   => 'nullable|string',
        ]);

        // 🚀 Normalização canônica da Mesa
        $tableId = $validated['dining_table_id'] ?? $validated['table_id'] ?? null;

        if ($tableId) {
            $validated['dining_table_id'] = (int) $tableId;
            $validated['table_id'] = (int) $tableId;
            if (empty($validated['table_number'])) {
                $validated['table_number'] = DiningTable::find($tableId)?->name;
            }
        } elseif (!empty($validated['table_number'])) {
            $foundTable = DiningTable::where('name', $validated['table_number'])->first();
            if ($foundTable) {
                $validated['dining_table_id'] = $foundTable->id;
                $validated['table_id'] = $foundTable->id;
            }
        }

        try {
            $this->logisticsService->transformOrder($order, $validated);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'order' => $order->fresh(['diningTable'])]);
            }

            return back()->with('success', 'Pedido transformado com sucesso!');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 400);
            }

            return back()->withErrors(['transform' => $e->getMessage()]);
        }
    }

    public function assignCourier(Order $order, Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $this->logisticsService->assignCourier($order, (int) $validated['driver_id']);
            return back()->with('success', 'Entregador atribuído com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['driver_id' => $e->getMessage()]);
        }
    }

    public function changeAddress(Order $order, Request $request)
    {
        $validated = $request->validate([
            'address_id'   => 'nullable',
            'address'      => 'nullable',
            'delivery_fee' => 'nullable|numeric|min:0',
        ]);

        try {
            $rawAddress = is_array($validated['address'] ?? null) ? $validated['address'] : null;
            $fee = isset($validated['delivery_fee']) ? (float) $validated['delivery_fee'] : null;

            $this->logisticsService->changeAddress($order, $validated['address_id'] ?? null, $fee, $rawAddress);
            return back()->with('success', 'Endereço atualizado com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['address' => $e->getMessage()]);
        }
    }

    public function updateDeliveryPayment(Order $order, Request $request)
    {
        $validated = $request->validate([
            'payment_intent_status'  => 'nullable|string',
            'payment_intent_methods' => 'nullable',
            'change_target'          => 'nullable|numeric',
        ]);

        $status = $validated['payment_intent_status'] ?? $order->payment_intent_status;
        $methods = $validated['payment_intent_methods'] ?? $order->payment_intent_methods;

        if (is_string($methods)) {
            $methods = json_decode($methods, true) ?: $methods;
        }

        $order->update([
            'payment_intent_status'  => $status,
            'payment_intent_methods' => $methods,
            'change_target'          => $validated['change_target'] ?? $order->change_target,
        ]);

        broadcast(new \App\Events\OrderUpdated($order->fresh()));

        return back()->with('success', 'Forma de pagamento de entrega atualizada!');
    }

    public function bulkCompleteDispatched()
    {
        $result = $this->logisticsService->bulkCompleteDispatched();

        if (!empty($result['skipped'])) {
            return back()
                ->with('success', "{$result['processed']} pedido(s) finalizado(s) com sucesso!")
                ->with('warning', "{$result['skipped']} pedido(s) não puderam ser baixados por pendência no valor ou forma de pagamento.");
        }

        return back()->with('success', "{$result['processed']} pedidos finalizados com sucesso!");
    }
}
