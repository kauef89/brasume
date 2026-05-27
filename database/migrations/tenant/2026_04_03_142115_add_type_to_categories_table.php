<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A trava de segurança: só tenta criar se a coluna NÃO existir
        if (!Schema::hasColumn('categories', 'type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('type')->default('external')->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};