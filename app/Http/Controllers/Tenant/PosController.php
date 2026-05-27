<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Pos\PosMenuService;
use App\Services\Pos\OrderProcessingService;

class PosController extends Controller
{
    public function index(Request $request, PosMenuService $menuService)
    {
        return Inertia::render("Pos/OrderEntry", $menuService->getOrderEntryData($request));
    }

    public function store(Request $request, OrderProcessingService $orderService)
    {
        $payload = $request->validate([
            "order_id" => "nullable|integer",
            "items" => "required|array",
            "routing" => "required|array",
            "financials" => "required|array",
        ]);

        $order = $orderService->processCheckout($payload);

        return redirect()->back()->with("success_order_id", $order->id);
    }

    public function terminal(Request $request, PosMenuService $menuService)
    {
        return Inertia::render("Pos/Terminal", $menuService->getTerminalData($request));
    }
}
