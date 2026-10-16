<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use App\Services\RestaurantOps\OperationsMenuService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosOrderEntryController extends Controller
{
    /**
     * Renderiza a tela isolada de lançamento de comandas (Mesa / Balcão).
     */
    public function index(Request $request, OperationsMenuService $menuService): Response
    {
        return Inertia::render("Pos/OrderEntry", $menuService->getOrderEntryData($request));
    }
}
