<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\CustomerReorderService;
use Illuminate\Http\Request;

class CustomerReorderController extends Controller
{
    public function store(Request $request, Order $order, CustomerReorderService $reorderService)
    {
        // 🚀 CORREÇÃO: Navegando pela relação auth()->user()->customer?->id
        $isOwner = auth()->check() && auth()->user()->customer?->id === $order->customer_id;
        $isSessionGuest = session('guest_customer_id') === $order->customer_id;

        if (!$isOwner && !$isSessionGuest) {
            abort(403, 'Acesso negado. Você não tem permissão para refazer este pedido.');
        }

        try {
            // A ação pode ser 'same' (Gerar novo PIX) ou 'change' (Mudar forma de pagamento)
            $action = $request->input('new_payment_method', 'same');

            $result = $reorderService->processReorder($order, $action);

            return back()->with('flash', [
                'success' => 'Pedido recriado com sucesso!',
                'pix_data' => $result['pix_data'] ?? null
            ]);

        } catch (\Exception $e) {
            return back()->with('flash', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
