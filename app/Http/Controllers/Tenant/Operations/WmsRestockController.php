<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Services\RestaurantOps\WmsRestockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WmsRestockController extends Controller
{
    protected WmsRestockService $wmsService;

    public function __construct(WmsRestockService $wmsService)
    {
        $this->wmsService = $wmsService;
    }

    public function index(Request $request)
    {
        // 🚀 Chama o método correto do WmsRestockService
        $restockBoard = $this->wmsService->getRestockBoard();

        if ($request->hasHeader('X-Inertia')) {
            return inertia('Operations/RestockBoard', [
                'restockData' => $restockBoard
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $restockBoard
        ]);
    }

    public function updateStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_id'     => 'required|integer|exists:product_storage_location,id',
            'added_stock' => 'required|numeric|min:0.01',
        ]);

        // 🚀 Chama o addStockToShelf e recebe a Model atualizada
        $rule = $this->wmsService->addStockToShelf($validated['rule_id'], (float) $validated['added_stock']);

        return response()->json([
            'success' => true,
            'message' => 'Reposição confirmada!',
            'data'    => [
                'rule_id'       => $rule->id,
                'current_stock' => (float) $rule->current_stock,
                'total_stock'   => (float) ($rule->product?->current_stock ?? 0),
            ]
        ]);
    }
}
