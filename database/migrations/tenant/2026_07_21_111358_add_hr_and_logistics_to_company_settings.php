<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Regras de RH (App do Colaborador)
            $table->boolean('has_food_allowance')->default(false)->after('cash_difference_tolerance');
            $table->string('allowance_frequency')->nullable()->after('has_food_allowance'); // 'daily' ou 'monthly'
            $table->string('allowance_scope')->nullable()->after('allowance_frequency'); // 'global' ou 'individual'
            $table->boolean('self_service_enabled')->default(false)->after('allowance_scope');

            // Regras de Logística (App do Motoboy)
            $table->json('attention_categories')->nullable()->after('self_service_enabled'); // Array de IDs de categorias
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'has_food_allowance',
                'allowance_frequency',
                'allowance_scope',
                'self_service_enabled',
                'attention_categories'
            ]);
        });
    }
};
