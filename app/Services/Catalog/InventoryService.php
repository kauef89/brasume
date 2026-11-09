<?php
namespace App\Services\Catalog;

use App\Services\Catalog\Inventory\Traits\HandlesStockMovements;
use App\Services\Catalog\Inventory\Traits\HandlesRecipeDeductions;
use App\Services\Catalog\Inventory\Traits\HandlesInventoryAudits;
use App\Services\Catalog\Inventory\Traits\HandlesStockAlerts;

class InventoryService
{
    use HandlesStockMovements;
    use HandlesRecipeDeductions;
    use HandlesInventoryAudits;
    use HandlesStockAlerts;
}
