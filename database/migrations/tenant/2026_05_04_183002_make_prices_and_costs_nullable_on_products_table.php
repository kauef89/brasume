<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table("products", function (Blueprint $table) {
            // Nota: Pelo seu código de formatação (5 casas decimais),
            // assumi que o tipo do custo é decimal(15,5) ou parecido.
            // Se o seu for diferente (ex: 10,2), ajuste os números abaixo!
            $table->decimal("base_cost", 15, 5)->nullable()->change();

            // Já garantindo os preços de venda como Nullable também
            $table->decimal("dine_in_price", 10, 2)->nullable()->change();
            $table->decimal("delivery_price", 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("products", function (Blueprint $table) {
            // Reverte para obrigatório (sem nullable) caso precise dar rollback
            $table->decimal("base_cost", 15, 5)->nullable(false)->change();
            $table->decimal("dine_in_price", 10, 2)->nullable(false)->change();
            $table->decimal("delivery_price", 10, 2)->nullable(false)->change();
        });
    }
};
