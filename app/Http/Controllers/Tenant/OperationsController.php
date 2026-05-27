<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Order;
use App\Services\Operations\OperationsService;

class OperationsController extends Controller
{
    protected $operationsService;

    public function __construct(OperationsService $operationsService)
    {
        $this->operationsService = $operationsService;
    }

    public function index()
    {
        return Inertia::render('Operations/ControlPanel', $this->operationsService->getActiveOrdersDashboard());
    }

    public function updateStatus(Order $order, Request $request)
    {
        $request->validate(['status' => 'required|string']);

        $this->operationsService->updateOrderStatus($order, $request->status);
        return redirect()->back()->with('success', 'Status do pedido atualizado!');
    }

    public function updateDeliveryPayment(Order $order, Request $request)
    {
        $order->update([
            'delivery_payment_status' => $request->delivery_payment_status,
            'delivery_payment_methods' => $request->delivery_payment_methods,
        ]);

        return redirect()->back();
    }
}
