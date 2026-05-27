<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table("legacy_products", function (Blueprint $table) {
            // 🚀 Agora aponta para a tabela de OPÇÕES!
            $table
                ->foreignId("current_modifier_id")
                ->nullable()
                ->constrained("modifier_options") // Correção aqui!
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table("legacy_products", function (Blueprint $table) {
            // Para dar rollback com segurança, também pegamos o nome dinâmico
            $tabelaModificadores = new \App\Models\ProductModifier()->getTable();

            $table->dropForeign(["current_modifier_id"]);
            $table->dropColumn("current_modifier_id");
        });
    }
};
