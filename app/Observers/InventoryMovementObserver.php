<?php

namespace App\Observers;

use App\Models\InventoryMovement;
use App\Services\Catalog\ProductCostingService;

class InventoryMovementObserver
{
    /**
     * Intercepta ANTES de salvar (creating) para forçar o user_id logado
     * caso o módulo que gerou a movimentação tenha esquecido de enviar.
     */
    public function creating(InventoryMovement $movement): void
    {
        if (empty($movement->user_id) && auth()->check()) {
            $movement->user_id = auth()->id();
        }
    }

    /**
     * Após salvar, limpa o cache de custo para que as fichas técnicas
     * absorvam as novas médias móveis do Kardex.
     */
    public function created(InventoryMovement $movement): void
    {
        ProductCostingService::clearCache();
    }
}