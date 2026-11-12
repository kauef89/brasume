<?php

namespace App\Observers;

use App\Enums\ProductType;
use App\Models\Product;
use App\Events\CatalogUpdated;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    public function saving(Product $product): void
    {
        // 1. Geração de Slug
        if (empty($product->slug) || $product->isDirty('name') || $product->isDirty('slug')) {
            $source = (!empty($product->slug) && $product->isDirty('slug')) ? $product->slug : $product->name;
            $product->slug = Str::slug($source ?? '');
        }

        // 🛡️ 2. BLINDAGEM DE IDENTIDADE: Insumos e Materiais Operacionais
        $operationalTypes = [ProductType::INGREDIENT->value, ProductType::CONSUMABLE->value, ProductType::PACKAGING->value, ProductType::SERVICE->value, ProductType::MANUFACTURED->value];

        if (in_array($product->type, $operationalTypes, true)) {
            // Insumos NUNCA aparecem em cardápios e não possuem preço ao consumidor
            $product->show_on_menu = false;
            $product->show_on_customer_app = false;
            $product->dine_in_price = null;
            $product->delivery_price = null;
            $product->promo_price = null;
            $product->requires_preparation = false;
        }

        // 🛡️ 3. BLINDAGEM DE PRODUTOS DE VENDA (resale, dish, combo)
        if (in_array($product->type, [ProductType::RESALE->value, ProductType::DISH->value, ProductType::COMBO->value], true)) {
            // Se for cadastrado como prato ou combo, obrigatoriamente requer preparo
            if (in_array($product->type, [ProductType::DISH->value, ProductType::COMBO->value], true)) {
                $product->requires_preparation = true;
            }
        }

        // 4. Gestão de status baseada no estoque físico
        if ($product->track_inventory) {
            if ($product->current_stock <= 0) {
                if (in_array($product->status, ['available', 'active'], true)) {
                    $product->status = 'out_of_stock';
                }
            } elseif ($product->current_stock > 0 && $product->status === 'out_of_stock') {
                $product->status = 'available';
            }
        }
    }

    public function saved(Product $product): void
    {
        // Se current_stock foi alterado via formulário Filament (save normal, não updateQuietly) e não há regras WMS
        if ($product->wasChanged('current_stock') && $product->track_inventory && !$product->storageRules()->exists()) {
            $oldStock = (float) $product->getOriginal('current_stock');
            $newStock = (float) $product->current_stock;
            $diff     = round($newStock - $oldStock, 4);

            if (abs($diff) >= 0.0001) {
                \Illuminate\Support\Facades\DB::table('inventory_movements')->insert([
                    'product_id'    => $product->id,
                    'type'          => 'adjustment',
                    'quantity'      => $diff,
                    'balance_after' => $newStock,
                    'unit_cost'     => (float) ($product->base_cost ?? 0),
                    'description'   => "Ajuste direto via Cadastro do Produto ({$oldStock} → {$newStock})",
                    'user_id'       => auth()->id(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
        // Se o preço de Salão ou Delivery mudou (ou produto novo de venda)
        if ($product->isDirty('dine_in_price') || $product->isDirty('delivery_price') || $product->wasRecentlyCreated) {
            if (in_array($product->type, [\App\Enums\ProductType::DISH->value, \App\Enums\ProductType::COMBO->value, \App\Enums\ProductType::RESALE->value])) {
                \App\Services\Catalog\ProductCostingService::recordPriceSnapshot($product, 'price_change');
            }
        }

        $this->flushCacheAndBroadcast($product);
    }

    public function deleted(Product $product): void
    {
        $this->flushCacheAndBroadcast($product);
    }

    public function forceDeleted(Product $product): void
    {
        try {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }
        } catch (\Exception $e) {
            Log::error("Erro ao apagar imagem física do Produto: " . $e->getMessage());
        }
    }

    private function flushCacheAndBroadcast(Product $product): void
    {
        $tenantId = $product->tenant_id ?? tenant('id') ?? 'central';

        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['catalog'])->flush();
            }
            Cache::forget('customer_catalog_data');
        } catch (\Exception $e) {
            Log::warning("Falha ao limpar cache do catálogo: " . $e->getMessage());
        }

        if (class_exists(CatalogUpdated::class)) {
            broadcast(new CatalogUpdated((string) $tenantId));
        }
    }
}
