<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configurações da Empresa
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('loyalty_is_active')->default(false)->after('kds_access_mode');
            $table->string('loyalty_reward_type')->default('cashback')->after('loyalty_is_active');
            $table->string('loyalty_accumulation_rule')->default('cart_value')->after('loyalty_reward_type');
            $table->decimal('loyalty_points_multiplier', 10, 2)->default(1.00)->after('loyalty_accumulation_rule');
            $table->decimal('loyalty_cashback_conversion_rate', 10, 2)->default(5.00)->after('loyalty_points_multiplier');
            $table->decimal('loyalty_min_redeem_value', 10, 2)->default(30.00)->after('loyalty_cashback_conversion_rate');
        });

        // 2. Tabela de Produtos (Custo e Ganho de pontos)
        Schema::table('products', function (Blueprint $table) {
            $table->integer('loyalty_points_cost')->nullable()->after('loyalty_reward')->comment('Quanto custa para o cliente resgatar?');
            $table->integer('loyalty_points_earned')->nullable()->after('loyalty_points_cost')->comment('Quantos pontos o cliente ganha se a regra for individual?');
        });

        // 3. 🚀 INJEÇÃO DA FORMA DE PAGAMENTO "CASHBACK"
        // Ela é criada com show_on_pos e show_on_delivery = false, para operadores não a selecionarem manualmente.
        DB::table('payment_methods')->insert([
            'name' => 'Cashback Fidelidade',
            'category' => 'marketing',
            'engine_type' => 'internal_loyalty',
            'show_on_pos' => 0,
            'show_on_delivery' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'loyalty_is_active',
                'loyalty_reward_type',
                'loyalty_accumulation_rule',
                'loyalty_points_multiplier',
                'loyalty_cashback_conversion_rate',
                'loyalty_min_redeem_value',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points_cost', 'loyalty_points_earned']);
        });

        DB::table('payment_methods')->where('engine_type', 'internal_loyalty')->delete();
    }
};
