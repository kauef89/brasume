<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_modifiers', function (Blueprint $table) {
            // Remove a ligação velha de Insumos (com trava de segurança caso o crash anterior já tenha apagado)
            if (Schema::hasColumn('legacy_modifiers', 'current_product_id')) {
                $table->dropForeign(['current_product_id']);
                $table->dropColumn('current_product_id');
            }

            // Cria a ligação nova apontando para a tabela correta do seu ERP!
            if (!Schema::hasColumn('legacy_modifiers', 'modifier_option_id')) {
                $table->foreignId('modifier_option_id')->nullable()->constrained('modifier_options')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('legacy_modifiers', function (Blueprint $table) {
            $table->dropForeign(['modifier_option_id']);
            $table->dropColumn('modifier_option_id');
            $table->foreignId('current_product_id')->nullable()->constrained('products')->nullOnDelete();
        });
    }
};
