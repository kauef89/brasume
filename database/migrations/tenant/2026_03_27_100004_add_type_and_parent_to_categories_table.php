<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verifica e cria a coluna 'type' isoladamente
        if (!Schema::hasColumn('categories', 'type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('type')->default('external')->after('name');
            });
        }
        
        // Verifica e cria a coluna 'parent_id' isoladamente
        if (!Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('type')->constrained('categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
        
        if (Schema::hasColumn('categories', 'type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};