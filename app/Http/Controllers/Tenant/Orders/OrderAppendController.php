<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosOrderSubmitRequest;
use App\Models\Order;
use App\Services\Orders\Handlers\OrderAppendHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OrderAppendController extends Controller
{
    public function store(
        Order $order,
        PosOrderSubmitRequest $request,
        OrderAppendHandler $appendHandler
    ): JsonResponse|RedirectResponse {
        Log::info("📍 [APPEND] Recebendo itens via PosOrderSubmitRequest para Pedido #{$order->id}", [
            'frontend_intent' => $request->input('frontend_intent')
        ]);

        if (!$appendHandler->canAppend($order->id)) {
            $msg = "Ação recusada: O Pedido #{$order->id} não pode receber novos itens neste momento.";

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        try {
            $payload = $request->validated();
            $updatedOrder = $appendHandler->handle($order->id, $payload);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'          => true,
                    'message'          => 'Itens adicionados com sucesso!',
                    'order_id'         => $updatedOrder->id,
                    'success_order_id' => $updatedOrder->id,
                    'order'            => $updatedOrder,
                ]);
            }

            return back()->with([
                'success'          => 'Itens adicionados com sucesso!',
                'order_id'         => $updatedOrder->id,
                'success_order_id' => $updatedOrder->id,
                'order'            => $updatedOrder,
            ]);

        } catch (\Exception $e) {
            Log::error("🚨 [APPEND CRASH]:", [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return back()->withErrors(['append' => $e->getMessage()]);
        }
    }
}
