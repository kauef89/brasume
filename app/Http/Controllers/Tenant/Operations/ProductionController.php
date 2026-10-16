<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProductionOrder;
use App\Services\RestaurantOps\ProductionService;

class ProductionController extends Controller
{
    public function __construct(
        protected ProductionService $productionService
    ) {}

    public function index()
    {
        $productionOrders = ProductionOrder::with('product')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('due_date', 'asc')
            ->get();

        return Inertia::render('Operations/SupplyChain/ProductionBoard', [
            'orders' => $productionOrders,
        ]);
    }

    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:production_orders,id',
            'status' => 'required|in:pending,in_progress,completed,cancelled'
        ]);

        try {
            $this->productionService->updateStatus($data['id'], $data['status']);
            return back()->with('success', 'Status atualizado com sucesso.');
        } catch (\Exception $e) {
            return back()->withErrors(['production' => 'Erro ao atualizar: ' . $e->getMessage()]);
        }
    }

    public function finish(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:production_orders,id',
            'actual_qty' => 'required|numeric|min:0'
        ]);

        try {
            // 🚀 Delega toda a regra de estoque e WebSockets para o Service
            $this->productionService->finishOrder($data['id'], $data['actual_qty']);

            return back()->with('success', 'Lote concluído! Estoque reabastecido.');
        } catch (\Exception $e) {
            return back()->withErrors(['production' => 'Erro ao finalizar lote: ' . $e->getMessage()]);
        }
    }
}
