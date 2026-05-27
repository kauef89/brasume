<?php
// Liga o motor do Laravel manualmente (sem precisar do Tinker)
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$calc = null;
$calc = function($prod, $lvl = 0) use (&$calc) {
    $sheets = \Illuminate\Support\Facades\DB::table('technical_sheets')->where('parent_product_id', $prod->id)->get();

    if($sheets->isEmpty()) {
        echo str_repeat("  ", $lvl) . "-> [ID:{$prod->id}] {$prod->name} (Cru) | base_cost: R$ " . ($prod->base_cost ?? 0) . "\n";
        return (float)($prod->base_cost ?? 0);
    }

    $total = 0;
    echo str_repeat("  ", $lvl) . "-> [ID:{$prod->id}] {$prod->name} (Composto) | rendimento: " . ($prod->recipe_yield ?? 1) . "\n";

    foreach($sheets as $s) {
        $c = \App\Models\Product::find($s->ingredient_product_id);
        if($c) {
            echo str_repeat("  ", $lvl+1) . "- Usa {$s->quantity} de [ID:{$c->id}] {$c->name}\n";
            $cost = $calc($c, $lvl + 2);
            $total += $cost * $s->quantity;
        } else {
            echo str_repeat("  ", $lvl+1) . "- [⚠️ ERRO] Insumo ID {$s->ingredient_product_id} não encontrado no banco!\n";
        }
    }

    $y = (float)($prod->recipe_yield ?? 1);
    $y = $y > 0 ? $y : 1;
    $f = $total / $y;

    echo str_repeat("  ", $lvl) . "=> Custo Final [ID:{$prod->id}]: R$ {$f}\n";
    return $f;
};

$p = \App\Models\Product::find(1554);
if ($p) {
    echo "\n=== INICIANDO RAIO-X DO PRODUTO {$p->id} ===\n";
    $calc($p);
    echo "=========================================\n\n";
} else {
    echo "Produto 1554 não encontrado.\n";
}
