<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Pode ser: 'pending_choice', 'pay_on_delivery', 'paid_online'
            $table->string('delivery_payment_status')->default('pending_choice')->after('payment_status');
            $table->string('delivery_payment_method')->nullable()->after('delivery_payment_status');
            $table->decimal('delivery_change_target', 10, 2)->nullable()->after('delivery_payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_payment_status', 'delivery_payment_method', 'delivery_change_target']);
        });
    }
};
