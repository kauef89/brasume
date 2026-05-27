<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adiciona na tabela de Grupos
        Schema::table('modifier_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('modifier_groups', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('type');
            }
        });

        // Adiciona na tabela de Opções (Respostas) - Verifique se o nome da sua tabela é esse mesmo
        // O padrão geralmente é 'modifier_options' ou 'product_modifier_options'
        $optionsTable = 'modifier_options'; // Mude aqui se a sua tabela de opções tiver outro nome
        
        if (Schema::hasTable($optionsTable)) {
            Schema::table($optionsTable, function (Blueprint $table) use ($optionsTable) {
                if (!Schema::hasColumn($optionsTable, 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('price');
                }
            });
        }
    }

    public function down(): void
    {
        // Não precisamos de lógica de down aqui para simplificar
    }
};