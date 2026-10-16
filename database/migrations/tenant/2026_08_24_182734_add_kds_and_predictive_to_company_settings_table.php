<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Adiciona a coluna do Preditivo de Sabores (IA)
            $table->string('predictive_modifiers_mode', 20)
                  ->default('disabled')
                  ->after('service_tax_percentage'); // Coloquei after para organizar, mas pode tirar se quiser

            // Adiciona a coluna de Gestão dos Painéis KDS
            $table->string('kds_panel_mode', 20)
                  ->default('all')
                  ->after('kds_access_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'predictive_modifiers_mode',
                'kds_panel_mode'
            ]);
        });
    }
};
