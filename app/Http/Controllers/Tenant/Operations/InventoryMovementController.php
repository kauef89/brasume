<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'new_stock' => 'required|numeric|min:0',
            'reason'    => 'required|string|max:255',
        ]);

        $this->inventoryService->adjustStock(
            $product,
            (float) $validated['new_stock'],
            $validated['reason'],
            auth()->id()
        );

        return back()->with('success', 'Estoque ajustado com sucesso!');
    }

    public function batchAdjust(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.location_id'   => 'nullable|exists:storage_locations,id',
            'items.*.counted_stock' => 'required|numeric|min:0',
            'reason'                => 'nullable|string|max:255',
        ]);

        $reason = $validated['reason'] ?? 'Balanço Físico Operacional (TeamApp)';
        $result = $this->inventoryService->batchAdjust($validated['items'], $reason, (int) auth()->id());

        $msg = 'Balanço salvo com sucesso!';
        if ($result['losses_count'] > 0) {
            $msg .= " ({$result['losses_count']} divergências de quebra registradas em auditoria).";
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'      => true,
                'message'      => $msg,
                'losses_count' => $result['losses_count'] ?? 0,
            ]);
        }

        return back()->with('success', $msg);
    }
}
