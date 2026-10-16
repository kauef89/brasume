<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('technical_sheets') && !Schema::hasColumn('technical_sheets', 'is_active')) {
            Schema::table('technical_sheets', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('order_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('technical_sheets') && Schema::hasColumn('technical_sheets', 'is_active')) {
            Schema::table('technical_sheets', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
