<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Produtos Legados (O "De -> Para" mora aqui)
        Schema::create("legacy_products", function (Blueprint $table) {
            $table->id();
            $table->string("legacy_code")->index()->comment("cProd do XML");
            $table->string("name")->comment("xProd do XML");
            $table->string("ean")->nullable()->comment("cEAN do XML");

            // A âncora para o produto atual!
            $table
                ->foreignId("current_product_id")
                ->nullable()
                ->constrained("products")
                ->nullOnDelete();
            $table->timestamps();
        });

        // 2. Formas de Pagamento Legadas
        Schema::create("legacy_payment_methods", function (Blueprint $table) {
            $table->id();
            $table
                ->string("sefaz_code")
                ->index()
                ->comment("tPag do XML (Ex: 01=Dinheiro, 03=Credito)");
            $table->string("name")->comment("Nome legível baseado no tPag");

            // A âncora para a forma de pagamento atual!
            $table
                ->foreignId("current_payment_method_id")
                ->nullable()
                ->constrained("payment_methods")
                ->nullOnDelete();
            $table->timestamps();
        });

        // 3. O Cabeçalho do Pedido Legado
        Schema::create("legacy_orders", function (Blueprint $table) {
            $table->id();
            $table->string("nfe_key", 44)->unique();
            $table->string("series")->nullable();
            $table->string("number")->nullable();
            $table->dateTime("issue_date");

            // Se tiver CPF/Nome, salva aqui. Se não, fica null!
            $table
                ->foreignId("customer_id")
                ->nullable()
                ->constrained("customers")
                ->nullOnDelete();

            $table->decimal("total_value", 12, 2);
            $table->decimal("discount", 12, 2)->default(0);
            $table->string("status")->default("completed");
            $table->timestamps();
        });

        // 4. Os Itens do Pedido Legado
        Schema::create("legacy_order_items", function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId("legacy_order_id")
                ->constrained("legacy_orders")
                ->cascadeOnDelete();
            $table
                ->foreignId("legacy_product_id")
                ->constrained("legacy_products")
                ->cascadeOnDelete();

            $table->decimal("quantity", 10, 3);
            $table->decimal("unit_price", 15, 5);
            $table->decimal("total_price", 15, 5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("legacy_order_items");
        Schema::dropIfExists("legacy_orders");
        Schema::dropIfExists("legacy_payment_methods");
        Schema::dropIfExists("legacy_products");
    }
};
