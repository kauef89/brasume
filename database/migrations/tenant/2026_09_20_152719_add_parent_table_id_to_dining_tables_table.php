<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            if (!Schema::hasColumn('dining_tables', 'parent_table_id')) {
                $table->foreignId('parent_table_id')
                      ->nullable()
                      ->after('table_area_id')
                      ->constrained('dining_tables')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            if (Schema::hasColumn('dining_tables', 'parent_table_id')) {
                $table->dropForeign(['parent_table_id']);
                $table->dropColumn('parent_table_id');
            }
        });
    }
};
