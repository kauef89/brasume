<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configurações da Empresa
        if (!Schema::hasColumn('company_settings', 'demand_forecast_mode')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->string('demand_forecast_mode')->default('manual_par')->after('cash_management_mode');
                $table->integer('production_buffer_days')->default(1)->after('demand_forecast_mode');
            });
        }

        // 2. Tabela de Produtos (se já tem min_stock, checamos pelo max_stock)
        if (!Schema::hasColumn('products', 'max_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('max_stock', 10, 3)->nullable()->after('min_stock');
                $table->decimal('par_level', 10, 3)->nullable()->after('max_stock');
            });
        }

        // 3. Tabela de Fornecedores
        if (!Schema::hasColumn('suppliers', 'order_days')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->json('order_days')->nullable()->after('type');
                $table->integer('lead_time_days')->default(1)->after('order_days');
            });
        }
    }

    public function down(): void
    {
        // Não é estritamente necessário preencher o down em colunas de tabelas críticas,
        // mas se quiser reverter, seria $table->dropColumn(...)
    }
};
