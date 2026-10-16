<?php

namespace App\Console\Commands;

use App\Services\RestaurantOps\WmsRestockService;
use Illuminate\Console\Command;

class SyncShiftRestockCommand extends Command
{
    protected $signature = 'wms:close-shift-restock {--shift= : ID do turno a consolidar}';
    protected $description = 'Consolida o consumo das prateleiras ao fechar o turno';

    public function handle(WmsRestockService $service): int
    {
        $shiftId = $this->option('shift') ? (int) $this->option('shift') : null;
        $this->info('Consolidando baixas de prateleira do turno...');

        $updated = $service->syncPendingConsumptionToShelf($shiftId);

        $this->info("Concluído: {$updated} locais de armazenamento tiveram seus saldos atualizados.");
        return self::SUCCESS;
    }
}
