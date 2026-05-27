<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class DebugCost extends Command
{
    // 🚀 Definimos que o comando recebe o ID do produto e o nome do tenant
    protected $signature = 'debug:cost {product_id} {--tenant= : O ID do tenant (ex: nativa)}';

    protected $description = 'Realiza um raio-X do custo de um produto em cascata';

    public function handle()
    {
        // 1. INICIALIZAMOS O TENANT (A sua sacada de mestre!)
        if ($this->option('tenant')) {
            tenancy()->initialize($this->option('tenant'));
            $this->info("✅ Tenant inicializado: " . $this->option('tenant'));
        }

        $productId = $this->argument('product_id');
        $product = Product::find($productId);

        if (!$product) {
            $this->error("❌ Produto {$productId} não encontrado neste tenant.");
            return;
        }

        $this->warn("\n=== INICIANDO RAIO-X DO PRODUTO {$product->id} ===");
        $this->calc($product, 0);
        $this->warn("==================================================\n");
    }

    private function calc($prod, $lvl = 0)
    {
        $sheets = DB::table('technical_sheets')->where('parent_product_id', $prod->id)->get();

        if($sheets->isEmpty()) {
            $this->line(str_repeat("  ", $lvl) . "-> [ID:{$prod->id}] {$prod->name} (Cru) | base_cost: R$ " . ($prod->base_cost ?? 0));
            return (float)($prod->base_cost ?? 0);
        }

        $total = 0;
        $this->info(str_repeat("  ", $lvl) . "-> [ID:{$prod->id}] {$prod->name} (Composto) | rendimento: " . ($prod->recipe_yield ?? 1));

        foreach($sheets as $s) {
            $c = Product::find($s->ingredient_product_id);
            if($c) {
                $this->line(str_repeat("  ", $lvl+1) . "- Usa {$s->quantity} de [ID:{$c->id}] {$c->name}");
                $cost = $this->calc($c, $lvl + 2);
                $total += $cost * $s->quantity;
            } else {
                $this->error(str_repeat("  ", $lvl+1) . "- [⚠️ ERRO] Insumo ID {$s->ingredient_product_id} não encontrado no banco!");
            }
        }

        $y = (float)($prod->recipe_yield ?? 1);
        $y = $y > 0 ? $y : 1;
        $f = $total / $y;

        $this->question(str_repeat("  ", $lvl) . "=> Custo Final [ID:{$prod->id}]: R$ {$f}");
        return $f;
    }
}


// USAR ASSIM NO TERMINAL: php artisan debug:cost XXXX --tenant=nativa [sendo XXXX o ID do produto]
