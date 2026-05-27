<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajustando a tabela de Produtos (Custo Atual)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_cost', 15, 5)->change();
        });

        // 2. Ajustando o Histórico de Custos (Entradas por Nota)
        Schema::table('product_cost_histories', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 5)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_cost', 10, 2)->change();
        });

        Schema::table('product_cost_histories', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 4)->change();
        });
    }
};