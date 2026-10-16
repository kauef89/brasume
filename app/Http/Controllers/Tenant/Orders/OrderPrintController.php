<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Printing\OrderPrintService;

class OrderPrintController extends Controller
{
    public function printDelivery(Order $order, Request $request, OrderPrintService $printService)
    {
        $settings = CompanySetting::getCached()?->toArray() ?? [];

        $isLoyaltyActive = !empty($settings['loyalty_is_active']) && !empty($settings['print_loyalty_flyer']);
        $isGrowthActive  = !empty($settings['print_growth_flyer']);

        $defaultLoyalty = $isLoyaltyActive && (($settings['loyalty_flyer_mode'] ?? '') === 'auto');
        $defaultGrowth  = $isGrowthActive  && (($settings['growth_flyer_mode'] ?? '') === 'auto');

        $requestLoyalty = $request->boolean('loyalty', $defaultLoyalty);
        $requestGrowth  = $request->boolean('growth', $defaultGrowth);

        try {
            $printService->printDeliveryDispatch($order, $requestLoyalty, $requestGrowth);
            return back()->with('success', 'Via do entregador enviada para impressão!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
