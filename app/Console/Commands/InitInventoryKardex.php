<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InitInventoryKardex extends Command
{
    protected $signature = 'inventory:init-kardex {--tenant=nativa : Identificador do tenant}';
    protected $description = 'Inaugura o livro-razão de estoque (Kardex) convertendo os saldos atuais em Movimentação Inicial.';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' não foi encontrado.");
            return 1;
        }

        tenancy()->initialize($tenant);
        $this->info("Conectado ao tenant: {$tenantId}");
        $this->info("Iniciando Marco Zero do Kardex...");

        $products = Product::withoutGlobalScopes()
            ->where('track_inventory', true)
            ->whereNull('deleted_at')
            ->get();

        $movements = [];
        $now = Carbon::now();

        foreach ($products as $product) {
            $stock = (float) $product->current_stock;

            if ($stock != 0) {
                $movements[] = [
                    'product_id'          => $product->id,
                    'storage_location_id' => null,
                    'type'                => 'adjustment',
                    'quantity'            => $stock,
                    'balance_after'       => $stock,
                    'unit_cost'           => (float) ($product->base_cost ?? 0),
                    'origin_type'         => null,
                    'origin_id'           => null,
                    'description'         => 'Implantação do Sistema Kardex (Saldo Inicial)',
                    'user_id'             => null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
        }

        if (!empty($movements)) {
            foreach (array_chunk($movements, 200) as $chunk) {
                DB::table('inventory_movements')->insert($chunk);
            }
        }

        $this->info('Kardex inicializado com sucesso para ' . count($movements) . ' produtos!');
        tenancy()->end();

        return 0;
    }
}
