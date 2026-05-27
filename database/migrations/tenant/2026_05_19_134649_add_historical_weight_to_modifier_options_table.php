<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('modifier_options', function (Blueprint $table) {
        // Usamos default(1) para que opções novas (sem histórico) entrem na média simples
        $table->unsignedInteger('historical_weight')->default(1)->after('is_active');
    });
}

public function down(): void
{
    Schema::table('modifier_options', function (Blueprint $table) {
        $table->dropColumn('historical_weight');
    });
}
};
