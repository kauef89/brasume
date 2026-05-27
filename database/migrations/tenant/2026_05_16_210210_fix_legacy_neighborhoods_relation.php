<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_neighborhoods', function (Blueprint $table) {
            // Remove a ligação antiga
            $table->dropForeign(['table_area_id']);
            $table->dropColumn('table_area_id');

            // Adiciona a ligação correta aos Bairros (Neighborhoods)
            $table->foreignId('neighborhood_id')->nullable()->after('legacy_name')->constrained('neighborhoods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legacy_neighborhoods', function (Blueprint $table) {
            $table->dropForeign(['neighborhood_id']);
            $table->dropColumn('neighborhood_id');
            $table->foreignId('table_area_id')->nullable()->constrained('table_areas')->nullOnDelete();
        });
    }
};
