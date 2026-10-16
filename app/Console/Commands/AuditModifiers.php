<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class AuditModifiers extends Command
{
    protected $signature = 'catalog:audit-modifiers {--tenant=}';
    protected $description = 'Auditoria investigativa de modificadores no banco de dados.';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        if (!$tenantId) {
            $this->error('Por favor, informe o tenant. Ex: --tenant=nativa');
            return Command::FAILURE;
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error('Tenant não encontrado.');
            return Command::FAILURE;
        }

        tenancy()->initialize($tenant);
        $this->info("🕵️ Iniciando auditoria no tenant: {$tenantId}...");

        // 1. Amostra bruta de modificadores vendidos recentemente
        $this->warn("\n1. Últimos 5 order_item_modifiers vendidos (Dados puros):");
        dump(DB::table('order_item_modifiers')->orderByDesc('id')->limit(5)->get()->toArray());

        // 2. Tipos de grupos existentes no banco
        $this->warn("\n2. Tipos de modifier_groups existentes no cardápio:");
        dump(DB::table('modifier_groups')
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get()
            ->toArray()
        );

        // 3. Teste do JOIN que fizemos (onde a informação evaporou)
        $this->warn("\n3. Cruzamento entre o item vendido e o grupo:");
        dump(
            DB::table('order_item_modifiers')
                ->leftJoin('modifier_groups', 'order_item_modifiers.modifier_group_id', '=', 'modifier_groups.id')
                ->select(
                    'order_item_modifiers.name',
                    'order_item_modifiers.modifier_option_id',
                    'order_item_modifiers.modifier_group_id',
                    'modifier_groups.type as group_type'
                )
                ->orderByDesc('order_item_modifiers.id')
                ->limit(5)
                ->get()
                ->toArray()
        );

        tenancy()->end();
        return Command::SUCCESS;
    }
}
