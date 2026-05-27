<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Só adiciona a coluna se ela NÃO existir na tabela
        if (!Schema::hasColumn('products', 'is_grab_and_go')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_grab_and_go')->default(false)->after('track_inventory');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'is_grab_and_go')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_grab_and_go');
            });
        }
    }
};