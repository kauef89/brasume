<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Adiciona a coluna como boolean (Verdadeiro/Falso) e define o padrão como "Sim"
            $table->boolean('shopping_list')->default(true)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('shopping_list');
        });
    }
};