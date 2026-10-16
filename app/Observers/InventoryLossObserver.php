<?php

namespace App\Observers;

use App\Models\InventoryLoss;
use App\Services\Catalog\InventoryService;

class InventoryLossObserver
{
    public function created(InventoryLoss $loss): void
    {
        if ($loss->skipInventoryDeduction || (float) $loss->quantity <= 0) {
            return;
        }

        app(InventoryService::class)->deductLoss($loss);
    }

    public function deleted(InventoryLoss $loss): void
    {
        if ($loss->skipInventoryDeduction || (float) $loss->quantity <= 0) {
            return;
        }

        app(InventoryService::class)->restoreLoss($loss);
    }
}
