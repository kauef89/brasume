<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Services\RestaurantOps\TablesService;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function __construct(protected TablesService $tablesService) {}

    public function updateStatus(Request $request, DiningTable $table)
    {
        $validated = $request->validate([
            // Modificado para 'available'
            'status' => 'required|string|in:available,occupied,reserved,cleaning,maintenance',
        ]);

        $this->tablesService->updateStatus($table, $validated['status']);

        $message = "Status da mesa {$table->name} alterado com sucesso.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function move(Request $request, DiningTable $table)
    {
        $validated = $request->validate([
            'target_table_id' => 'required|exists:dining_tables,id',
        ]);

        $targetTable = $this->tablesService->move($table, (int) $validated['target_table_id']);

        $message = "Comanda transferida da {$table->name} para {$targetTable->name}.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function join(Request $request, DiningTable $table)
    {
        $validated = $request->validate([
            'secondary_table_ids'   => 'required|array|min:1',
            'secondary_table_ids.*' => 'required|integer|exists:dining_tables,id|different:table',
        ]);

        $this->tablesService->join($table, $validated['secondary_table_ids']);

        $message = "Mesas agrupadas na {$table->name} com sucesso.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function split(Request $request, DiningTable $table)
    {
        $this->tablesService->split($table);

        $message = "Mesa {$table->name} desmembrada com sucesso.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
