<?php

namespace App\Observers;

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
        $operationalTypes = ['ingredient', 'supply', 'consumable', 'packaging', 'service', 'manufactured', 'internal'];

        if (in_array($product->type, $operationalTypes, true)) {
            // Insumos NUNCA aparecem em cardápios e não possuem preço ao consumidor
            $product->show_on_menu = false;
            $product->show_on_delivery = false;
            $product->dine_in_price = null;
            $product->delivery_price = null;
            $product->promo_price = null;
            $product->requires_preparation = false;
        }

        // 🛡️ 3. BLINDAGEM DE PRODUTOS DE VENDA (resale, dish, combo)
        if (in_array($product->type, ['resale', 'dish', 'combo'], true)) {
            // Se for cadastrado como prato ou combo, obrigatoriamente requer preparo
            if (in_array($product->type, ['dish', 'combo'], true)) {
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
