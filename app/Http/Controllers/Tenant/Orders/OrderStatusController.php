<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderCancellationService;
use App\Services\Orders\OrderReactivationService;
use App\Services\RestaurantOps\OperationsDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderStatusController extends Controller
{
    public function update(Order $order, Request $request, OperationsDashboardService $operationsService): RedirectResponse
    {
        $validated = $request->validate([
            'status'              => 'required|string',
            'stamp_intermediates' => 'nullable|boolean',
        ]);

        $operationsService->updateOrderStatus(
            $order,
            $validated['status'],
            $request->boolean('stamp_intermediates')
        );

        return redirect()->back()->with('success', 'Status do pedido atualizado!');
    }

    public function cancel(Order $order, Request $request, OrderCancellationService $cancellationService): RedirectResponse
    {
        $validated = $request->validate([
            'reason'     => 'nullable|string',
            'is_loss'    => 'nullable|boolean',
            'lost_items' => 'nullable|array',
            'pin'        => 'nullable|string',
        ]);

        $cancellationService->cancelOrder($order, $validated);

        return back()->with('success', 'Pedido cancelado e estornado com sucesso.');
    }

    public function cancelItem(Order $order, Request $request, OrderCancellationService $cancellationService): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:order_items,id',
            'qty'     => 'nullable',
            'is_loss' => 'nullable|boolean',
            'reason'  => 'nullable|string',
            'pin'     => 'nullable|string',
        ]);

        try {
            $cancellationService->cancelItem($order, $validated);
            return back()->with('success', 'Item estornado com sucesso!');
        } catch (\Exception $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }
    }

    public function reactivate(Order $order, Request $request, OrderReactivationService $reactivationService): RedirectResponse
    {
        $validated = $request->validate([
            'pin'    => 'nullable|string',
            'reason' => 'nullable|string|min:15|max:255',
            'mode'   => 'required|string|in:reactivate,correct_payment'
        ], [
            'reason.min' => 'A justificativa para a SEFAZ deve conter no mínimo 15 caracteres.'
        ]);

        try {
            $reactivationService->reactivate(
                $order,
                $validated['mode'],
                $validated['reason'] ?? null,
                $validated['pin'] ?? null
            );

            return back()->with('success', 'Conta reativada com sucesso.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->withErrors(['reactivate' => $e->getMessage()]);
        }
    }
}
