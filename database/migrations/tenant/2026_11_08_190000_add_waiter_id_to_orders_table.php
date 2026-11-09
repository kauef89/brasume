<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'waiter_id')) {
                $table->foreignId('waiter_id')
                    ->nullable()
                    ->after('cashier_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'waiter_id')) {
                $table->dropForeign(['waiter_id']);$table->dropColumn('waiter_id');
            }
        });
    }
};
