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
    Schema::table('product_modifier', function (Blueprint $table) {
        // Adiciona a coluna para o Filament conseguir salvar a ordem do drag-and-drop
        $table->integer('sort_order')->default(0)->after('modifier_id');
    });
}

public function down(): void
{
    Schema::table('product_modifier', function (Blueprint $table) {
        $table->dropColumn('sort_order');
    });
}
};
