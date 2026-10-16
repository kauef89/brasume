<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajustes na tabela de Cupons (com trava de existência)
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'customer_id')) {
                $table->foreignId('customer_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('customers')
                      ->nullOnDelete()
                      ->comment('Exclusividade do cliente (CRM)');
            }

            if (!Schema::hasColumn('coupons', 'usage_count')) {
                $table->integer('usage_count')
                      ->default(0)
                      ->after('user_usage_limit')
                      ->comment('Cache rápido de utilizações');
            }
        });

        // 2. Rastreabilidade na tabela de Pedidos
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'coupon_id')) {
                $table->foreignId('coupon_id')
                      ->nullable()
                      ->after('customer_id')
                      ->constrained('coupons')
                      ->nullOnDelete()
                      ->comment('Cupom utilizado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'coupon_id')) {
                $table->dropForeign(['coupon_id']);
                $table->dropColumn('coupon_id');
            }
        });

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('coupons', 'usage_count')) {
                $table->dropColumn('usage_count');
            }
        });
    }
};
