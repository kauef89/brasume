<?php

namespace App\Http\Controllers\Tenant\Operations;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\InventoryLoss;
use App\Events\CatalogUpdated;
use App\Services\Catalog\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryMovementController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function adjust(Request $request, Product $product)
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

    public function batchAdjust(Request $request)
    {
        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.location_id'   => 'nullable|exists:storage_locations,id',
            'items.*.counted_stock' => 'required|numeric|min:0',
            'reason'                => 'nullable|string|max:255',
        ]);

        $userId = auth()->id();
        $reason = $validated['reason'] ?? 'Balanço Físico Operacional (TeamApp)';
        $lossesCount = 0;
        $totalLossAmount = 0.0;

        DB::transaction(function () use ($validated, $userId, $reason, &$lossesCount, &$totalLossAmount) {
            foreach ($validated['items'] as $entry) {
                $product = Product::find($entry['product_id']);
                if (!$product || !$product->track_inventory) continue;

                $locationId = !empty($entry['location_id']) ? (int) $entry['location_id'] : null;
                $newLocationStock = (float) $entry['counted_stock'];

                if ($locationId) {
                    $pivot = DB::table('product_storage_location')
                        ->where('product_id', $product->id)
                        ->where('storage_location_id', $locationId)
                        ->first();

                    $oldLocationStock = $pivot ? (float) $pivot->current_stock : 0.0;
                    $locationDelta = round($newLocationStock - $oldLocationStock, 4);

                    DB::table('product_storage_location')->updateOrInsert(
                        ['product_id' => $product->id, 'storage_location_id' => $locationId],
                        ['current_stock' => $newLocationStock, 'updated_at' => now()]
                    );

                    $sumLocations = (float) DB::table('product_storage_location')
                        ->where('product_id', $product->id)
                        ->sum('current_stock');

                    $loc = StorageLocation::find($locationId);
                    $locName = $loc?->name ?? "Local #{$locationId}";

                    // 🚀 KARDEX RECORD
                    $this->inventoryService->logMovement(
                        product: $product,
                        type: 'adjustment',
                        quantity: $locationDelta,
                        newBalance: $sumLocations,
                        description: "Balanço Mobile ({$locName}): {$reason}",
                        locationId: $locationId
                    );

                    $product->update(['current_stock' => $sumLocations]);

                    if ($locationDelta < 0) {
                        $lossQty = abs($locationDelta);

                        $lossRecord = $this->inventoryService->registerLoss(
                            product: $product,
                            qty: $lossQty,
                            reason: "Furo de Balanço: {$locName}",
                            notes: "Contagem física: {$newLocationStock} {$product->unit_of_measure} (Anterior: {$oldLocationStock}). Motivo: {$reason}",
                            userId: $userId,
                            orderItemId: null,
                            lossType: InventoryLoss::TYPE_INVENTORY_AUDIT,
                            storageLocationId: $locationId,
                            skipDeduction: true // 🚀 Impede dedução dupla
                        );

                        $lossesCount++;
                        $totalLossAmount += (float) $lossRecord->total_cost;
                    }
                } else {
                    $oldStock = (float) $product->current_stock;
                    $delta = round($newLocationStock - $oldStock, 4);

                    $this->inventoryService->adjustStock(
                        $product,
                        $newLocationStock,
                        $reason,
                        $userId
                    );

                    if ($delta < 0) {
                        $lossesCount++;
                    }
                }

                $freshStock = (float) $product->fresh()->current_stock;
                if ($freshStock <= 0) {
                    if ($product->status === 'available') {
                        $this->inventoryService->triggerStockRupture($product);
                    }
                } else {
                    if ($product->status === 'out_of_stock') {
                        $this->inventoryService->triggerStockRestored($product);
                    }
                }
            }
        });

        broadcast(new CatalogUpdated(tenant('id') ?? 'central'));

        $msg = "Balanço salvo com sucesso!";
        if ($lossesCount > 0) {
            $msg .= " ({$lossesCount} divergências de quebra registradas em auditoria).";
        }

        return back()->with('success', $msg);
    }
}
