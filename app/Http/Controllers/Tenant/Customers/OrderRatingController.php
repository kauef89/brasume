<?php

namespace App\Http\Controllers\Tenant\Customers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderRatingController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        // 🔒 1. Exige autenticação oficial do cliente
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Você precisa estar logado para avaliar este pedido.'
            ], 401);
        }

        // 🔍 2. Localiza o perfil de cliente vinculado ao usuário
        $customer = $user->customer;

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil de cliente não localizado para esta conta.'
            ], 403);
        }

        // 🛡️ 3. Validação estrita de posse por ID
        if ((int) $order->customer_id !== (int) $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido não pertence à sua conta cadastrada.'
            ], 403);
        }

        // 📦 4. Regra de negócio: Apenas pedidos concluídos/entregues podem ser avaliados
        if (!in_array($order->status, ['delivered', 'completed', 'closed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas pedidos já entregues ou finalizados podem ser avaliados.'
            ], 422);
        }

        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'tags'    => ['nullable', 'array'],
            'tags.*'  => ['string', 'max:50'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $orderRating = OrderRating::updateOrCreate(
            ['order_id' => $order->id],
            [
                'customer_id' => $customer->id,
                'rating'      => $validated['rating'],
                'tags'        => $validated['tags'] ?? [],
                'comment'     => $validated['comment'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Avaliação registrada com sucesso!',
            'data'    => $orderRating,
        ]);
    }
}
