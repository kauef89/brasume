<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create("legacy_neighborhoods", function (Blueprint $table) {
            $table->id();
            $table
                ->string("legacy_name")
                ->unique()
                ->comment("Bairro exato do CSV");
            // Mapeia para a TableArea/Bairro real da V3 que tem as taxas cadastradas
            $table
                ->foreignId("table_area_id")
                ->nullable()
                ->constrained("table_areas")
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("legacy_neighborhoods");
    }
};
