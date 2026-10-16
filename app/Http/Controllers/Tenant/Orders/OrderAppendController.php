<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\Handlers\OrderAppendHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class OrderAppendController extends Controller
{
    public function store(Order $order, Request $request, OrderAppendHandler $appendHandler): JsonResponse|RedirectResponse
    {
        $payload = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required',
            'items.*.qty'      => 'required|numeric|min:0.01',
            'items.*.price'    => 'required|numeric',
            'financials'       => 'nullable|array',
            'routing'          => 'nullable|array',
            'source'           => 'nullable|string',
            'frontend_intent'  => 'nullable|string'
        ]);

        if (!$appendHandler->canAppend($order->id)) {
            $msg = "Ação recusada: O Pedido #{$order->id} não pode receber novos itens neste momento (pode estar fechado ou já liquidado).";

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        try {
            $updatedOrder = $appendHandler->handle($order->id, $payload);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'  => true,
                    'message'  => 'Itens adicionados com sucesso!',
                    'order_id' => $updatedOrder->id,
                    'order'    => $updatedOrder,
                ]);
            }

            return back()->with('success', 'Itens adicionados com sucesso!');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return back()->withErrors(['append' => $e->getMessage()]);
        }
    }
}
