<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Atualizações na tabela de Configurações da Empresa
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('tax_regime')
                  ->default('mei')
                  ->after('ie')
                  ->comment('mei, simples, presumido, real');

            $table->decimal('simples_rate', 5, 2)
                  ->default(0.00)
                  ->after('tax_regime')
                  ->comment('Alíquota do Simples Nacional (%)');

            $table->decimal('icms_rate', 5, 2)
                  ->default(0.00)
                  ->after('simples_rate')
                  ->comment('Alíquota Padrão de ICMS Estadual (%)');
        });

        // 2. Atualizações na tabela de Produtos
        Schema::table('products', function (Blueprint $table) {
            // O Rendimento da panela (Ex: entram 2.1kg de insumos, saem 1.8kg de preparado)
            $table->decimal('recipe_yield', 10, 4)
                  ->default(1.0000)
                  ->after('unit_of_measure')
                  ->comment('Rendimento do preparo (usado para tipo manufactured)');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['tax_regime', 'simples_rate', 'icms_rate']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('recipe_yield');
        });
    }
};
