<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Adiciona a coluna antes de updated_at para manter a organização
            if (!Schema::hasColumn('products', 'is_monofasico')) {
                $table->boolean('is_monofasico')->default(false)->after('fiscal_cest');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_monofasico')) {
                $table->dropColumn('is_monofasico');
            }
        });
    }
};