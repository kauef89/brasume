<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create("legacy_customers", function (Blueprint $table) {
            $table->id();
            $table
                ->string("legacy_name")
                ->unique()
                ->comment("Nome exato do CSV");
            // Mapeia para o cliente real da V3 (opcional, para cruzar relatórios futuros)
            $table
                ->foreignId("customer_id")
                ->nullable()
                ->constrained("customers")
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("legacy_customers");
    }
};
