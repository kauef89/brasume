<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_sheets', function (Blueprint $table) {
            // Cria uma coluna JSON para guardar o array de cenários escolhidos
            $table->json('scenarios')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheets', function (Blueprint $table) {
            $table->dropColumn('scenarios');
        });
    }
};