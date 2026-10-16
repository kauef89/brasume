<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ShoppingListItem;
use App\Services\SupplyChain\ProcurementService;

class ProcurementController extends Controller
{
    public function index(ProcurementService $procurementService)
    {
        $smartMatrix = $procurementService->getSmartBiddingMatrix();

        return Inertia::render('Operations/SupplyChain/ShoppingList', [
            'biddingMatrix' => $smartMatrix,
        ]);
    }

    public function removeItem(Request $request)
    {
        $request->validate(['id' => 'required|exists:shopping_list_items,id']);

        // Mantém o histórico, apenas cancela a intenção
        ShoppingListItem::find($request->id)->update(['status' => 'cancelled']);

        return back()->with('success', 'Item removido da lista de intenções.');
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:shopping_list_items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
        ]);

        foreach ($data['items'] as $itemData) {
            ShoppingListItem::where('id', $itemData['id'])->update([
                'status' => 'ordered',
                'approved_qty' => $itemData['qty']
            ]);
        }

        return back()->with('success', 'Pedido fechado e enviado ao fornecedor!');
    }
}
