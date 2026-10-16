<?php

namespace App\Http\Controllers\Tenant\Orders;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Services\Orders\OrderTransferService;
use Illuminate\Http\Request;

class OrderTransferController extends Controller
{
    public function store(Request $request, OrderTransferService $service)
    {
        $validated = $request->validate([
            'source_order_id'             => 'required|integer|exists:orders,id',
            'item_ids'                    => 'required|array',
            'destination_type'            => 'required|string|in:table,counter_delivery',
            'destination_table_id'        => 'nullable|integer|exists:dining_tables,id',
            'destination_dining_table_id' => 'nullable|integer|exists:dining_tables,id',
            'destination_table_name'      => 'nullable|string',
            'destination_order_intent'    => 'nullable|string',
            'new_subaccount_name'         => 'nullable|string',
        ]);

        // 🚀 Normalização canônica da mesa de destino
        $destTableId = $validated['destination_dining_table_id'] ?? $validated['destination_table_id'] ?? null;

        if ($destTableId && empty($validated['destination_table_name'])) {
            $validated['destination_table_name'] = DiningTable::find($destTableId)?->name;
        } elseif (!empty($validated['destination_table_name']) && !$destTableId) {
            $foundTable = DiningTable::where('name', $validated['destination_table_name'])->first();
            if ($foundTable) {
                $destTableId = $foundTable->id;
            }
        }

        if ($destTableId) {
            $validated['destination_dining_table_id'] = (int) $destTableId;
            $validated['destination_table_id'] = (int) $destTableId;
        }

        try {
            $service->execute($validated);

            if ($request->wantsJson()) return response()->json(['success' => true]);
            return back()->with('success', 'Itens transferidos com sucesso!');

        } catch (\Exception $e) {
            if ($request->wantsJson()) return response()->json(['error' => $e->getMessage()], 400);
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }
    }
}
