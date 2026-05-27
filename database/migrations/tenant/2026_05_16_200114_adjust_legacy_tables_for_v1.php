<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Ajustes na tabela de Pedidos Legados
        Schema::table("legacy_orders", function (Blueprint $table) {
            // Torna a chave da NFe opcional (para a V1)
            $table->string("nfe_key")->nullable()->change();

            // Adiciona o Tipo do Pedido
            $table
                ->string("order_type")
                ->nullable()
                ->after("status")
                ->comment("delivery, takeaway, dine_in");

            // Adiciona as FKs para os mapeamentos novos
            $table
                ->foreignId("legacy_customer_id")
                ->nullable()
                ->after("customer_id")
                ->constrained("legacy_customers")
                ->nullOnDelete();
            $table
                ->foreignId("legacy_neighborhood_id")
                ->nullable()
                ->after("legacy_customer_id")
                ->constrained("legacy_neighborhoods")
                ->nullOnDelete();
        });

        // 2. Ajustes na tabela de Formas de Pagamento Legadas
        Schema::table("legacy_payment_methods", function (Blueprint $table) {
            // Torna o código da Sefaz opcional (pois na V1 usaremos os cabeçalhos do CSV)
            $table->string("sefaz_code")->nullable()->change();
        });

        // 3. Nova Tabela de Pagamentos dos Pedidos Legados
        Schema::create("legacy_order_payments", function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId("legacy_order_id")
                ->constrained("legacy_orders")
                ->cascadeOnDelete();
            $table
                ->foreignId("legacy_payment_method_id")
                ->constrained("legacy_payment_methods")
                ->cascadeOnDelete();
            $table->decimal("amount", 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("legacy_order_payments");

        Schema::table("legacy_payment_methods", function (Blueprint $table) {
            $table->string("sefaz_code")->nullable(false)->change();
        });

        Schema::table("legacy_orders", function (Blueprint $table) {
            $table->dropForeign(["legacy_neighborhood_id"]);
            $table->dropForeign(["legacy_customer_id"]);
            $table->dropColumn([
                "legacy_neighborhood_id",
                "legacy_customer_id",
                "order_type",
            ]);
            $table->string("nfe_key")->nullable(false)->change();
        });
    }
};
