<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosOrderSubmitRequest;
use App\Models\Order;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\Handlers\OrderAppendHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OrderCreationController extends Controller
{
    public function store(PosOrderSubmitRequest $request, OrderCreationService $creationService, OrderAppendHandler $appendHandler): JsonResponse|RedirectResponse
    {
        $payload = $request->validated();

        $targetOrderId = $payload['order_id']
            ?? $payload['routing']['id']
            ?? $payload['routing']['order_id']
            ?? $payload['routing']['existing_order_id']
            ?? null;

        // 🚀 O pulo do gato: Se chegou um ID na Rota de Criação, nós faremos o Append forçado pelo Backend
        if ($targetOrderId) {
            $existingOrder = Order::find($targetOrderId);

            // Regras de Bloqueio para não anexar em comanda que já expirou
            $isFinished = $existingOrder && (
                in_array($existingOrder->status, ['closed', 'cancelled', 'completed', 'merged'], true)
                || ($existingOrder->type === 'table' && $existingOrder->payment_status === 'paid')
            );

            if ($isFinished) {
                Log::warning("🛑 [OrderCreation] Pedido #{$targetOrderId} fechado. Criando nova comanda.");
                $payload['order_id'] = null;
                if (isset($payload['routing']) && is_array($payload['routing'])) {
                    $payload['routing']['id'] = null;
                    $payload['routing']['order_id'] = null;
                    $payload['routing']['existing_order_id'] = null;
                }
            } else {
                Log::info("🔄 [OrderCreation] Detectado ID #{$targetOrderId} na rota de Store. Delegando para AppendHandler.");

                try {
                    // Força a chamada do fluxo de adição em vez de criar um novo
                    $updatedOrder = $appendHandler->handle((int)$targetOrderId, $payload);

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
                    if ($request->wantsJson()) return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
                    return back()->withErrors(['append' => $e->getMessage()]);
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
