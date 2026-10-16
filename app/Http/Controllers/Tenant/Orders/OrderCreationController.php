<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosOrderSubmitRequest;
use App\Models\Order;
use App\Services\Orders\OrderCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OrderCreationController extends Controller
{
    public function store(PosOrderSubmitRequest $request, OrderCreationService $creationService): JsonResponse|RedirectResponse
    {
        $payload = $request->validated();

        $targetOrderId = $payload['order_id']
            ?? $payload['routing']['id']
            ?? $payload['routing']['order_id']
            ?? $payload['routing']['existing_order_id']
            ?? null;

        if ($targetOrderId) {
            $existingOrder = Order::find($targetOrderId);

            // 🛡️ REGRAS DE BLOQUEIO:
            // 1. Status operacional já fechado ou cancelado (qualquer canal)
            // 2. MESA QUITADA: Se for mesa e já estiver paga, a conta encerrou! Cria nova comanda.
            $isFinished = $existingOrder && (
                in_array($existingOrder->status, ['closed', 'cancelled', 'completed', 'merged'], true)
                || ($existingOrder->type === 'table' && $existingOrder->payment_status === 'paid')
            );

            if ($isFinished) {
                Log::warning("🛑 [OrderCreation] Pedido #{$targetOrderId} não aceita append (Status: {$existingOrder->status}, Tipo: {$existingOrder->type}, Pagamento: {$existingOrder->payment_status}). Abrindo nova comanda.");

                $payload['order_id'] = null;
                if (isset($payload['routing']) && is_array($payload['routing'])) {
                    $payload['routing']['id'] = null;
                    $payload['routing']['order_id'] = null;
                    $payload['routing']['existing_order_id'] = null;
                }
            }
        }

        try {
            $order = $creationService->processCheckout($payload);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'          => true,
                    'message'          => 'Pedido processado com sucesso!',
                    'order_id'         => $order->id,
                    'success_order_id' => $order->id,
                    'order'            => $order,
                ], 201);
            }

            return back()->with([
                'success'          => 'Pedido processado com sucesso!',
                'order_id'         => $order->id,
                'success_order_id' => $order->id,
                'order'            => $order,
            ]);

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }
    }
}
