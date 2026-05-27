<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Como o 'type' já existe, adicionamos apenas o 'unit_measure' logo após ele
            $table->string('unit_measure')->default('un')->after('type'); // 'un', 'g', 'kg', 'ml', 'l'
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unit_measure']);
        });
    }
};