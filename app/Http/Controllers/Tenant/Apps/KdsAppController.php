<?php

namespace App\Http\Controllers\Tenant\Apps;

use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Services\RestaurantOps\KdsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KdsAppController extends Controller
{
    public function index(Request $request, KdsService $kdsService, $kitchen_id = 'all'): Response
    {
        return Inertia::render("Kds/KdsApp", [
            'kitchens'        => Kitchen::where('is_active', true)->get(),
            'activeKitchenId' => $kitchen_id,
            'initialItems'    => $kdsService->getItems($kitchen_id),
        ]);
    }
}
