<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Order;
use App\Services\Pos\CheckoutService;

class CheckoutController extends Controller
{
    protected $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    public function show(Order $order)
    {
        return Inertia::render('Pos/Checkout', $this->checkoutService->getCheckoutData($order));
    }

    public function quickAdd(Order $order, Request $request)
    {
        $request->validate(['item_id' => 'required|integer']);

        $this->checkoutService->quickAdd($order, (int) $request->item_id);
        return redirect()->back()->with('success', 'Quantidade atualizada!');
    }

    public function cancelItem(Order $order, Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|integer',
            'pin' => 'required|string',
            'reason' => 'required|string',
            'is_loss' => 'nullable|boolean',
        ]);

        try {
            $this->checkoutService->cancelItem($order, $data);
            return redirect()->back()->with('success', 'Item removido do pedido.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['pin' => $e->getMessage()]);
        }
    }

    public function processPayment(Order $order, Request $request)
    {
        $data = $request->validate([
            'payments' => 'required|array',
            'items' => 'required|array',
            'service_fee' => 'nullable|numeric'
        ]);

        $this->checkoutService->processPayment($order, $data);
        return redirect()->back()->with('success', 'Pagamento processado!');
    }
}
