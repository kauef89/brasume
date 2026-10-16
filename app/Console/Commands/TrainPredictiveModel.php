<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;

class TrainPredictiveModel extends Command
{
    // O tenant agora é opcional (?)
    protected $signature = 'pos:train-ai {tenant?}';
    protected $description = 'Treina a IA de Sabores para uma ou todas as lanchonetes';

    public function handle()
    {
        $tenantId = $this->argument('tenant');

        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
            if ($tenants->isEmpty()) {
                $this->error("❌ Lanchonete '{$tenantId}' não encontrada!");
                return;
            }
        } else {
            $this->info("🌐 Nenhuma lanchonete especificada. A iniciar treinamento global...");
            $tenants = Tenant::all();
        }

        foreach ($tenants as $tenant) {
            $this->trainForTenant($tenant);
        }

        $this->info("🎉 Treinamento global concluído com sucesso!");
    }

    private function trainForTenant($tenant)
    {
        tenancy()->initialize($tenant);
        $this->info("🧠 Treinando IA para: {$tenant->id}...");

        $matrix = [];
        $weights = [];

        // 1. LEGADO (V1)
        $legacyData = DB::table('legacy_order_item_modifiers as lom')
            ->join('legacy_modifiers as lm', 'lom.legacy_modifier_id', '=', 'lm.id')
            ->whereNotNull('lm.modifier_option_id')
            ->select('lom.legacy_order_item_id as item_id', 'lm.modifier_option_id as opt_id')
            ->get()
            ->groupBy('item_id');

        foreach ($legacyData as $itemId => $mods) {
            $ids = $mods->pluck('opt_id')->unique()->toArray();
            foreach ($ids as $idA) {
                $weights[$idA] = ($weights[$idA] ?? 0) + 1;
                if (!isset($matrix[$idA])) $matrix[$idA] = [];
                foreach ($ids as $idB) {
                    if ($idA != $idB) $matrix[$idA][$idB] = ($matrix[$idA][$idB] ?? 0) + 1;
                }
            }
        }

        // 2. NOVOS PEDIDOS (V3)
        $newData = DB::table('order_item_modifiers')
            ->whereNotNull('modifier_option_id')
            ->select('order_item_id as item_id', 'modifier_option_id as opt_id')
            ->get()
            ->groupBy('item_id');

        foreach ($newData as $itemId => $mods) {
            $ids = $mods->pluck('opt_id')->unique()->toArray();
            foreach ($ids as $idA) {
                $weights[$idA] = ($weights[$idA] ?? 0) + 1;
                if (!isset($matrix[$idA])) $matrix[$idA] = [];
                foreach ($ids as $idB) {
                    if ($idA != $idB) $matrix[$idA][$idB] = ($matrix[$idA][$idB] ?? 0) + 1;
                }
            }
        }

        // 3. ATUALIZA BBDD E CACHE
        $updatedCount = 0;
        foreach ($weights as $optId => $weight) {
            $updatedCount += DB::table('modifier_options')->where('id', $optId)->update(['historical_weight' => $weight]);
        }

        Cache::store('file')->forever('predictive_modifier_matrix', $matrix);

        // 🚀 AVISA O FRONTEND APENAS SE HOUVE ATUALIZAÇÃO
        if ($updatedCount > 0 || !empty($matrix)) {
            broadcast(new \App\Events\CatalogUpdated());
            $this->info("✅ {$tenant->id} treinada e apps notificados! (" . count($matrix) . " conexões).");
        } else {
            $this->info("⚠️ {$tenant->id} processada, mas não havia dados suficientes para treinar a IA.");
        }

        tenancy()->end();
    }
}
