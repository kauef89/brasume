<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\Orders\CourierSettlementService;
use App\Services\Orders\OrderLogisticsService;
use Illuminate\Http\Request;

class OrderLogisticsController extends Controller
{
    public function __construct(
        protected OrderLogisticsService $logisticsService,
        protected CourierSettlementService $courierSettlementService
    ) {}

    public function assignCourier(Order $order, Request $request)
    {
        $validated = $request->validate([
            'courier_id'           => 'nullable|integer',
            'driver_id'            => 'nullable|integer',
            'delivery_distance_km' => 'nullable|numeric|min:0',
        ]);

        try {
            $rawId = $validated['courier_id'] ?? $validated['driver_id'] ?? null;
            if (!$rawId) {
                throw new \Exception('Selecione um entregador válido.');
            }

            $courier = CourierSettlementService::resolveEmployee((int) $rawId);
            if (!$courier) {
                throw new \Exception('Entregador não encontrado no cadastro de colaboradores.');
            }

            $distance = isset($validated['delivery_distance_km']) ? (float) $validated['delivery_distance_km'] : null;
            $this->courierSettlementService->assignCourier($order, $courier, $distance);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Entregador atribuído com sucesso!',
                    'order'   => $order->fresh(['courier', 'driver'])
                ]);
            }

            return back()->with('success', 'Entregador atribuído e taxa fixada com sucesso!');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['courier_id' => $e->getMessage()]);
        }
    }

    public function transform(Order $order, Request $request)
    {
        $validated = $request->validate([
            'new_type'        => 'nullable|string|in:table,counter,delivery,pickup',
            'newType'         => 'nullable|string|in:table,counter,delivery,pickup',
            'type'            => 'nullable|string|in:table,counter,delivery,pickup',
            'table_id'        => 'nullable|integer|exists:dining_tables,id',
            'dining_table_id' => 'nullable|integer|exists:dining_tables,id',
            'table_number'    => 'nullable|string',
            'identifier'      => 'nullable|string',
            'customer_name'   => 'nullable|string',
        ]);

        $tableId = $validated['dining_table_id'] ?? $validated['table_id'] ?? null;
        if ($tableId) {
            $validated['dining_table_id'] = (int) $tableId;
            $validated['table_id'] = (int) $tableId;
            if (empty($validated['table_number'])) {
                $validated['table_number'] = DiningTable::find($tableId)?->name;
            }
        }

        try {
            $this->logisticsService->transformOrder($order, $validated);
            return back()->with('success', 'Pedido transformado com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['transform' => $e->getMessage()]);
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

        return back()->with('success', 'Forma de pagamento atualizada!');
    }

    public function bulkCompleteDispatched()
    {
        $result = $this->logisticsService->bulkCompleteDispatched();
        return back()->with('success', "{$result['processed']} pedidos finalizados com sucesso!");
    }

    public function applyDiscount(Order $order, \Illuminate\Http\Request $request, \App\Services\Orders\PosCheckoutService $checkoutService)
    {
        $validated = $request->validate([
            'discount'    => 'required|numeric|min:0',
            'coupon_id'   => 'nullable|integer',
            'coupon_code' => 'nullable|string',
        ]);

        $checkoutService->applyDiscount($order, (float) $validated['discount']);

        $couponId = !empty($validated['coupon_id']) ? (int) $validated['coupon_id'] : null;
        if (!$couponId && !empty($validated['coupon_code'])) {
            $foundCoupon = \App\Models\Coupon::where('code', $validated['coupon_code'])->first();
            $couponId = $foundCoupon?->id;
        }

        if ($couponId) {
            $order->updateQuietly(['coupon_id' => $couponId]);
        }

        return response()->json(['success' => true, 'order' => $order->fresh(['items', 'coupon'])]);
    }

    public function removeDiscount(Order $order, \App\Services\Orders\PosCheckoutService $checkoutService)
    {
        $checkoutService->removeDiscount($order);
        $order->updateQuietly(['coupon_id' => null]);

        return response()->json(['success' => true, 'order' => $order->fresh(['items', 'coupon'])]);
    }
}
