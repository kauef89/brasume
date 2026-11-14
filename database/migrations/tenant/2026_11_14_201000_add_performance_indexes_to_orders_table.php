<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasIndex('orders', 'orders_status_updated_at_index')) {
                $table->index(['status', 'updated_at'], 'orders_status_updated_at_index');
            }
            if (!Schema::hasIndex('orders', 'orders_created_payment_status_index')) {
                $table->index(['created_at', 'payment_status', 'status'], 'orders_created_payment_status_index');
            }
            if (!Schema::hasIndex('orders', 'orders_type_status_payment_index')) {
                $table->index(['type', 'status', 'payment_status'], 'orders_type_status_payment_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_updated_at_index');
            $table->dropIndex('orders_created_payment_status_index');
            $table->dropIndex('orders_type_status_payment_index');
        });
    }
};
