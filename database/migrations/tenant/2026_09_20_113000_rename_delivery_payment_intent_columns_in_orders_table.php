<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_payment_status') && !Schema::hasColumn('orders', 'payment_intent_status')) {
                $table->renameColumn('delivery_payment_status', 'payment_intent_status');
            }

            if (Schema::hasColumn('orders', 'delivery_payment_methods') && !Schema::hasColumn('orders', 'payment_intent_methods')) {
                $table->renameColumn('delivery_payment_methods', 'payment_intent_methods');
            }

            if (Schema::hasColumn('orders', 'delivery_change_target') && !Schema::hasColumn('orders', 'change_target')) {
                $table->renameColumn('delivery_change_target', 'change_target');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_intent_status') && !Schema::hasColumn('orders', 'delivery_payment_status')) {
                $table->renameColumn('payment_intent_status', 'delivery_payment_status');
            }

            if (Schema::hasColumn('orders', 'payment_intent_methods') && !Schema::hasColumn('orders', 'delivery_payment_methods')) {
                $table->renameColumn('payment_intent_methods', 'delivery_payment_methods');
            }

            if (Schema::hasColumn('orders', 'change_target') && !Schema::hasColumn('orders', 'delivery_change_target')) {
                $table->renameColumn('change_target', 'delivery_change_target');
            }
        });
    }
};
