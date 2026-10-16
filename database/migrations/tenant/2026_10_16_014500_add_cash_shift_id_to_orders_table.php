<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'cash_shift_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('cash_shift_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('cash_shifts')
                    ->nullOnDelete();

                $table->index('cash_shift_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'cash_shift_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['cash_shift_id']);
                $table->dropColumn('cash_shift_id');
            });
        }
    }
};
