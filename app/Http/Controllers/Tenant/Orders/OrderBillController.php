<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\PosCheckoutService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderBillController extends Controller
{
    public function __construct(protected PosCheckoutService $checkoutService) {}

    public function show(Request $request, Order $order)
    {
        $payload = $this->checkoutService->getCheckoutData($order);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return Inertia::render('Pos/Checkout', $payload);
    }

    public function quickAdd(Request $request, Order $order)
    {
        $itemId = (int) $request->input('item_id');
        $shouldPrint = $request->boolean('should_print', false);

        $newItem = $this->checkoutService->quickAdd($order, $itemId, $shouldPrint);

        return back()->with('success', "Item {$newItem->name} duplicado (+1).");
    }

    public function addItem(Request $request, Order $order)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'nullable|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'notes'      => 'nullable|string',
        ]);

        $item = $this->checkoutService->addItem($order, $validated);

        return back()->with('success', "Item {$item->name} adicionado ao pedido.");
    }

    public function cancelItem(Request $request, Order $order, ?OrderItem $item = null)
    {
        $itemId = $item ? $item->id : (int) $request->input('item_id');
        $qty = $request->input('qty', 'all');
        $reason = $request->input('reason', 'Cancelado no checkout');

        $result = $this->checkoutService->cancelItem($order, $itemId, $qty, $reason);

        return back()->with(
            'success',
            $result['is_partial']
                ? "{$result['cancelled_qty']} unidade(s) cancelada(s) com sucesso."
                : 'Item cancelado com sucesso.'
        );
    }

    public function applyDiscount(Request $request, Order $order)
    {
        $validated = $request->validate([
            'discount' => 'required|numeric|min:0',
            'reason'   => 'nullable|string',
        ]);

        $this->checkoutService->applyDiscount($order, (float) $validated['discount']);

        return back()->with('success', 'Desconto aplicado.');
    }

    public function removeDiscount(Order $order)
    {
        $this->checkoutService->removeDiscount($order);

        return back()->with('success', 'Desconto removido.');
    }

    public function toggleServiceFee(Request $request, Order $order)
    {
        $this->checkoutService->toggleServiceFee($order, $request->boolean('enabled'));

        return back();
    }

    public function linkCustomer(Request $request, Order $order)
    {
        $validated = $request->validate([
            'customer_id'   => 'nullable|integer',
            'customer_name' => 'nullable|string',
        ]);

        $this->checkoutService->linkCustomer(
            $order,
            $validated['customer_id'] ?? null,
            $validated['customer_name'] ?? null
        );

        return back()->with('success', 'Cliente vinculado ao pedido.');
    }
}
