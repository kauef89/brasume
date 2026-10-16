<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RestaurantOps\OperationsDashboardService;
use App\Services\RestaurantOps\PosMenuService;
use App\Services\Orders\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Order $order, PosMenuService $posMenuService, OperationsDashboardService $operationsService): Response
    {
        $flatItems = OrderItem::withoutGlobalScopes()
            ->with(['modifiers', 'product.kitchen.printer'])
            ->where('order_id', $order->id)
            ->get();

        $order->setRelation('items', $flatItems);

        // 🚀 Carregamento ansioso do relacionamento relacional deliveryAddress
        $order->load([
            'customer.addresses.street',
            'customer.addresses.neighborhood',
            'deliveryAddress.street',
            'deliveryAddress.neighborhood',
            'payments',
            'rating',
            'diningTable.tableArea',
        ]);

        return Inertia::render('Operations/OrderDetails', [
            'order'           => $order,
            'tableAreas'      => $posMenuService->getTableAreas(),
            'companySettings' => CompanySetting::getCached(),
            'couriers'        => $operationsService->getAvailableCouriers(),
        ]);
    }

    public function toggleRating(Request $request, Order $order, OrderStatusService $statusService): RedirectResponse
    {
        $statusService->toggleRatingSuppression($order, $request->boolean('skip_rating'));

        return back();
    }
}
