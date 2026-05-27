<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create("legacy_daily_revenues", function (Blueprint $table) {
            $table->id();
            // A data é única para não termos duas consolidações no mesmo dia
            $table->date("date")->unique();

            // O Total Geral do Dia (Obrigatório)
            $table->decimal("total_revenue", 12, 2);

            // Opcionais que o Wizard pode ou não mapear
            $table
                ->integer("order_count")
                ->nullable()
                ->comment("Qtd de Pedidos");
            $table
                ->decimal("expenses", 12, 2)
                ->nullable()
                ->comment("Despesas do Dia");

            // Arrays associativos guardados como JSON (Ex: {"Dinheiro": 100, "Pix": 500})
            $table
                ->json("payments_breakdown")
                ->nullable()
                ->comment("Subtotais por Pagamento");
            $table
                ->json("types_breakdown")
                ->nullable()
                ->comment("Subtotais por Tipo (Delivery/Mesa)");

            $table->string("import_tag")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("legacy_daily_revenues");
    }
};
