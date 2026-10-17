<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\Handlers\OrderAppendHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OrderAppendController extends Controller
{
    public function store(Order $order, Request $request, OrderAppendHandler $appendHandler): JsonResponse|RedirectResponse
    {
        Log::info("📍 [SONDA APPEND 1] Recebido no Controller para o Pedido #{$order->id}", [
            'frontend_intent' => $request->input('frontend_intent')
        ]);

        try {
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
            Log::info("📍 [SONDA APPEND 2] Validação passou com sucesso.");
        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::error("🚨 [SONDA APPEND ERRO] Validação falhou:", $ve->errors());
            throw $ve;
        }

        if (!$appendHandler->canAppend($order->id)) {
            $msg = "Ação recusada: O Pedido #{$order->id} não pode receber novos itens neste momento.";
            Log::warning("⚠️ [SONDA APPEND 3] Bloqueado pelo canAppend()");

            if ($request->wantsJson()) return response()->json(['success' => false, 'message' => $msg], 422);
            return back()->with('error', $msg);
        }

        try {
            Log::info("📍 [SONDA APPEND 4] Iniciando Handler...");
            $updatedOrder = $appendHandler->handle($order->id, $payload);
            Log::info("📍 [SONDA APPEND 5] Handler concluído sem erros!");

            if ($request->wantsJson()) {
                return response()->json([
                    'success'  => true,
                    'message'  => 'Itens adicionados com sucesso!',
                    'order_id' => $updatedOrder->id,
                    'order'    => $updatedOrder,
                ]);
            }

            return back()->with([
                'success' => 'Itens adicionados com sucesso!',
                'order_id' => $updatedOrder->id
            ]);

        } catch (\Exception $e) {
            Log::error("🚨 [SONDA APPEND ERRO FATAL] Crash no Handler:", [
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
