<?php

namespace App\Http\Controllers\Tenant\RestaurantOps;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RestaurantOps\KdsService;
use App\Services\Printing\OrderPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KdsProductionController extends Controller
{
    public function updateStatus(Request $request, OrderItem $item, KdsService $kdsService): RedirectResponse
    {
        $request->validate(['production_status' => 'required|string|max:50']);
        $kdsService->updateItemStatus($item, $request->production_status);

        return redirect()->back();
    }

    public function sendItemsToKitchen(Request $request, Order $order, KdsService $kdsService): JsonResponse
    {
        $validated = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $kdsService->dispatchItemsToKitchen($order, $validated['items']);

            return response()->json([
                'success' => true,
                'message' => 'Itens enviados para a cozinha com sucesso!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Falha ao enviar itens para a cozinha: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function registerLoss(Request $request, OrderItem $item, KdsService $kdsService): RedirectResponse
    {
        $validated = $request->validate([
            'type'      => 'required|string',
            'target_id' => 'required|integer',
            'quantity'  => 'required|numeric|min:0.001',
            'reason'    => 'required|string',
            'notes'     => 'nullable|string',
        ]);

        $kdsService->registerLoss($item, $validated, auth()->id());
        return redirect()->back();
    }

    public function cancelItem(Request $request, OrderItem $item, KdsService $kdsService): RedirectResponse
    {
        $validated = $request->validate([
            'reason'      => 'required|string',
            'block_sales' => 'boolean',
            'block_type'  => 'nullable|string',
            'block_id'    => 'nullable|integer',
        ]);

        $kdsService->cancelItemAndRefund($item, $validated);
        return redirect()->back();
    }

    public function printItem(Request $request, OrderItem $item, OrderPrintService $printService): RedirectResponse
    {
        try {
            $requestedQty = $request->input('qty', 'all');
            $originalQty = (float) ($item->quantity ?: 1);

            $item->loadMissing([
                'order',
                'modifiers',
                'product.kitchen.printer',
                'product.category',
                'subItems.modifiers'
            ]);

            $printableItem = clone $item;
            $printableItem->setRelations($item->getRelations());

            if ($requestedQty === 'single' || (is_numeric($requestedQty) && (float) $requestedQty < $originalQty)) {
                $targetQty = ($requestedQty === 'single') ? 1.0 : (float) $requestedQty;
                $printableItem->quantity = $targetQty;

                $ratio = $targetQty / $originalQty;

                if ($item->relationLoaded('modifiers')) {
                    $scaledModifiers = $item->modifiers->map(function ($mod) use ($ratio) {
                        $clonedMod = clone $mod;
                        $clonedMod->quantity = max(1, (int) round(($clonedMod->quantity ?: 1) * $ratio));
                        return $clonedMod;
                    });
                    $printableItem->setRelation('modifiers', $scaledModifiers);
                }

                if ($item->relationLoaded('subItems')) {
                    $scaledSubItems = $item->subItems->map(function ($sub) use ($ratio) {
                        $clonedSub = clone $sub;
                        $clonedSub->quantity = max(1, (int) round(($clonedSub->quantity ?: 1) * $ratio));
                        return $clonedSub;
                    });
                    $printableItem->setRelation('subItems', $scaledSubItems);
                }
            }

            $printService->reprintKdsItem($printableItem);
            return back()->with('success', 'Via enviada para impressão!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * 🚀 Impressão em lote de itens selecionados (Bulk Print)
     */
    public function bulkPrint(Request $request, OrderPrintService $printService)
    {
        $validated = $request->validate([
            'item_ids'   => 'required|array|min:1',
            'item_ids.*' => 'required|integer|exists:order_items,id',
        ]);

        try {
            $items = OrderItem::with([
                'order',
                'modifiers',
                'product.kitchen.printer',
                'product.category',
                'subItems.modifiers'
            ])
            ->whereIn('id', $validated['item_ids'])
            ->get();

            foreach ($items as $item) {
                $printableItem = clone $item;
                $printableItem->setRelations($item->getRelations());
                $printService->reprintKdsItem($printableItem);
            }

            $count = $items->count();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$count} item(ns) enviado(s) para impressão!",
                ]);
            }

            return back()->with('success', "{$count} item(ns) enviado(s) para impressão!");
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Falha na impressão em lote: ' . $e->getMessage());
        }
    }
}
