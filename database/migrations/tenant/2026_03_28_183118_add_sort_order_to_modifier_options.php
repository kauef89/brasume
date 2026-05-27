<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_options', function (Blueprint $table) {
            if (!Schema::hasColumn('modifier_options', 'sort_order')) {
                // Adiciona a coluna de ordem, com valor padrão 0
                $table->integer('sort_order')->default(0)->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('modifier_options', function (Blueprint $table) {
            if (Schema::hasColumn('modifier_options', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};