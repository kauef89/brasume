<?php

tenancy()->initialize('nativa');

$items = \App\Models\Product::where('track_consumption', true)->get();

echo PHP_EOL . "================================================================================" . PHP_EOL;
echo "  AUDITORIA DO MOTOR DE CADENCIA (TENANT NATIVA)" . PHP_EOL;
echo "================================================================================" . PHP_EOL;
printf("%-5s | %-28s | %-14s | %-12s | %-8s\n", "ID", "NOME", "CICLO", "ULT. COMPRA", "STATUS");
echo "--------------------------------------------------------------------------------" . PHP_EOL;

$reporCount = 0;

foreach ($items as $p) {
    $last = \App\Models\ProductCostHistory::where('product_id', $p->id)->latest('id')->first();
    $lastDate = $last ? \Carbon\Carbon::parse($last->created_at)->format('d/m/Y') : 'Sem compras';
    $cycle = $p->consumption_cycle_days ? ($p->consumption_cycle_days . 'd (fixo)') : 'Auto (media)';

    $repor = 'OK';
    if (method_exists($p, 'getConsumptionReplenishmentStatus')) {
        $st = $p->getConsumptionReplenishmentStatus();
        if (!empty($st['needs_restock'])) {
            $repor = 'REPOR';
            $reporCount++;
        }
    }

    $name = mb_substr($p->name, 0, 28);
    printf("%-5d | %-28s | %-14s | %-12s | %-8s\n", $p->id, $name, $cycle, $lastDate, $repor);
}

echo "--------------------------------------------------------------------------------" . PHP_EOL;
echo sprintf("Total monitorados por cadencia: %d | Sugestao de reposicao imediata: %d", $items->count(), $reporCount) . PHP_EOL;
echo "================================================================================" . PHP_EOL . PHP_EOL;
