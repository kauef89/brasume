<?php

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\AvailabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AvailabilityController extends Controller
{
    public function __construct(protected AvailabilityService $availabilityService) {}

    public function index()
    {
        $catalog = Product::with(['category' => function ($q) {
                $q->active();
            }])
            ->active()
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        return Inertia::render('Operations/Availability', [
            'catalog' => $catalog,
        ]);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'action'     => 'required|in:available,paused,out_of_stock',
            'new_stock'  => 'nullable|numeric|min:0',
        ]);

        $this->availabilityService->updateAvailability(
            (int) $data['product_id'],
            $data['action'],
            isset($data['new_stock']) ? (float) $data['new_stock'] : null
        );

        return back()->with('success', 'Disponibilidade atualizada!');
    }

    public function registerLoss(Request $request)
    {
        $data = $request->validate([
            'target_id' => 'required|exists:products,id',
            'quantity'  => 'required|numeric|min:0.001',
            'reason'    => 'required|string|max:255',
        ]);

        $this->availabilityService->registerLoss($data, (int) auth()->id());

        return back()->with('success', 'Perda registrada com sucesso!');
    }
}
