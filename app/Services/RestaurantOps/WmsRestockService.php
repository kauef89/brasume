<?php

namespace App\Services\RestaurantOps;

use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStorageLocation;
use App\Models\StorageLocation;
use App\Models\CashShift;
use App\Events\CatalogUpdated;
use App\Services\Catalog\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WmsRestockService
{
    /**
     * Retorna o quadro de reposição para o painel (TeamApp / Retaguarda).
     */
    public function getRestockBoard(?int $shiftId = null): array
    {
        $settings = CompanySetting::first();
        $mode = $settings->wms_restock_mode ?? 'free';

        $soldProducts = [];
        if ($mode === 'sales_driven') {
            $soldProducts = $this->calculatePendingSales($shiftId);
        }

        $locations = StorageLocation::query()
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->whereIn('type', ['display', 'intermediate'])
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function (StorageLocation $location) use ($mode, $soldProducts) {

                $rules = ProductStorageLocation::query()
                    ->where('storage_location_id', $location->id)
                    ->whereHas('product', function ($q) {
                        $q->where('products.track_inventory', true)
                          ->where('products.requires_preparation', false)
                          ->where('products.type', \App\Enums\ProductType::RESALE->value)
                          ->where('products.status', 'available')
                          ->where(function ($sub) {
                              // 🛡️ BLINDAGEM: Produto de revenda precisa obrigatoriamente ter preço de venda
                              $sub->whereNotNull('products.dine_in_price')
                                  ->orWhereNotNull('products.delivery_price');
                          })
                          ->whereNull('products.deleted_at');
                    })
                    ->with(['product.category'])
                    ->get();

                $items = $rules->map(function ($rule) use ($mode, $soldProducts) {
                    $product = $rule->product;

                    // Salvaguarda caso o relacionamento venha corrompido
                    if (!$product || $product->status !== 'available' || $product->type !== \App\Enums\ProductType::RESALE->value) {
                        return null;
                    }

                    $suggestedRestock = 0;
                    $virtualStock = (float) $rule->current_stock;
                    $isCritical = false;

                    if ($mode === 'sales_driven') {
                        $soldQty = (float) ($soldProducts[$product->id] ?? 0);
                        $virtualStock = $rule->current_stock - $soldQty;
                        $spaceLeft = max(0, $rule->max_capacity - $virtualStock);
                        $suggestedRestock = min($soldQty, $spaceLeft);
                    }

                    if ($virtualStock <= $rule->min_level) {
                        $isCritical = true;
                    }

                    $totalStock = (float) ($product->current_stock ?? 0);

                    return [
                        'rule_id'          => $rule->id,
                        'product_id'       => $product->id,
                        'name'             => $product->name,
                        'category_id'      => $product->category_id,
                        'type'             => $product->type,
                        'category_name'    => $product->category?->name ?? 'Geral',
                        'category_order'   => $product->category?->sort_order ?? 999,
                        'unit_of_measure'  => $product->unit_of_measure ?? 'un',
                        'source_location'  => $rule->source_location ?? 'Almoxarifado Principal',
                        'current_stock'    => max(0, $virtualStock),
                        'max_capacity'     => (float) $rule->max_capacity,
                        'min_level'        => (float) $rule->min_level,
                        'is_critical'      => $isCritical,
                        'suggested_restock'=> max(0, $suggestedRestock),
                        'total_stock'      => $totalStock,
                        'status'           => $product->status,
                    ];
                })->filter(function ($item) use ($mode) {
                    if (!$item) return false;

                    if ($mode === 'sales_driven') {
                        return $item['suggested_restock'] > 0 || $item['is_critical'];
                    }

                    return true;
                })
                ->sort(function ($a, $b) {
                    $catOrderA = $a['category_order'] ?? 999;
                    $catOrderB = $b['category_order'] ?? 999;
                    if ($catOrderA !== $catOrderB) {
                        return $catOrderA <=> $catOrderB;
                    }

                    $catNameA = $a['category_name'] ?? '';
                    $catNameB = $b['category_name'] ?? '';
                    if ($catNameA !== $catNameB) {
                        return strcasecmp($catNameA, $catNameB);
                    }

                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                })
                ->values();

                return [
                    'location_id'    => $location->id,
                    'location_name'  => $location->name,
                    'restock_mode'   => $mode,
                    'critical_count' => $items->where('is_critical', true)->count(),
                    'items'          => $items,
                ];
            })->filter(fn ($loc) => $loc['items']->count() > 0)->values();

        return $locations->toArray();
    }

    public function calculateRestockNeeds(?int $shiftId = null): array
    {
        return $this->getRestockBoard($shiftId);
    }

    protected function calculatePendingSales(?int $shiftId): array
    {
        $query = Order::query()->whereNotIn('status', ['cancelled', 'pending']);

        if ($shiftId) {
            $query->where('cash_shift_id', $shiftId);
        } else {
            $activeShift = CashShift::where('status', 'open')->latest()->first();
            if ($activeShift) {
                $query->where('cash_shift_id', $activeShift->id);
            }
        }

        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.id', $query->select('id'))
            ->where('order_items.status', '!=', 'cancelled')
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_qty'))
            ->groupBy('order_items.product_id')
            ->pluck('total_qty', 'product_id')
            ->toArray();
    }

    public function addStockToShelf(int $ruleId, float $addedStock): ProductStorageLocation
    {
        return DB::transaction(function () use ($ruleId, $addedStock) {
            ProductStorageLocation::$skipAutoKardex = true;
            try {
                $rule = ProductStorageLocation::lockForUpdate()->findOrFail($ruleId);
                $product = Product::lockForUpdate()->find($rule->product_id);

                $beforeShelf = (float) $rule->current_stock;
                $targetShelf = $beforeShelf + $addedStock;
                if ($rule->max_capacity > 0 && $targetShelf > $rule->max_capacity) {
                    $targetShelf = (float) $rule->max_capacity;
                }

                $actualMoved = round($targetShelf - $beforeShelf, 3);
                if ($actualMoved <= 0) {
                    return $rule->load('product');
                }

                $rule->current_stock = $targetShelf;
                $rule->saveQuietly();

                // Se houver local de origem (ex: Estoque Seco -> Geladeira), transfere entre locais sem inflar saldo global
                if (!empty($rule->source_location_id)) {
                    $sourceRule = ProductStorageLocation::where('product_id', $rule->product_id)
                        ->where('storage_location_id', $rule->source_location_id)
                        ->lockForUpdate()
                        ->first();

                    if ($sourceRule && (float) $sourceRule->current_stock > 0) {
                        $deductFromSource = min((float) $sourceRule->current_stock, $actualMoved);
                        $sourceRule->current_stock = round((float) $sourceRule->current_stock - $deductFromSource, 3);
                        $sourceRule->saveQuietly();
                    }
                }

                if ($product) {
                    $newTotalStock = round((float) ProductStorageLocation::where('product_id', $product->id)->sum('current_stock'), 3);
                    $oldTotalStock = (float) $product->current_stock;
                    $globalDiff    = round($newTotalStock - $oldTotalStock, 4);

                    if (abs($globalDiff) >= 0.0001) {
                        app(InventoryService::class)->logMovement(
                            $product,
                            'adjustment',
                            $globalDiff,
                            $newTotalStock,
                            "Reposição WMS Prateleira #{$ruleId}",
                            null,
                            $rule->storage_location_id
                        );
                    }

                    $product->current_stock = $newTotalStock;
                    if ($product->status === 'out_of_stock' && $newTotalStock > 0) {
                        app(InventoryService::class)->triggerStockRestored($product);
                    } else {
                        $product->saveQuietly();
                        broadcast(new CatalogUpdated(tenant('id') ?? 'central'));
                    }
                }

                Log::info("📦 [WmsRestock] Reposição salva: +{$actualMoved} na regra #{$ruleId} (Produto: {$rule->product_id})");

                return $rule->load('product');
            } finally {
                ProductStorageLocation::$skipAutoKardex = false;
            }
        });
    }

    public function updateLocationStock(int $ruleId, float $addedStock): ProductStorageLocation
    {
        return $this->addStockToShelf($ruleId, $addedStock);
    }

    public function syncPendingConsumptionToShelf(?int $shiftId = null): int
    {
        $soldProducts = $this->calculatePendingSales($shiftId);
        if (empty($soldProducts)) {
            return 0;
        }

        $affectedRules = 0;

        DB::transaction(function () use ($soldProducts, &$affectedRules) {
            foreach ($soldProducts as $productId => $qtySold) {
                $rules = ProductStorageLocation::where('product_id', $productId)->get();

                if ($rules->isEmpty()) continue;

                $remainingToDeduct = $qtySold;

                foreach ($rules as $rule) {
                    if ($remainingToDeduct <= 0) break;

                    if ($rule->current_stock > 0) {
                        $deduct = min($rule->current_stock, $remainingToDeduct);
                        $rule->current_stock -= $deduct;
                        $rule->save();

                        $remainingToDeduct -= $deduct;
                        $affectedRules++;
                    }
                }
            }
        });

        return $affectedRules;
    }
}
