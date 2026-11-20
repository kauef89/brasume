<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'tip_amount')) {
                $table->decimal('tip_amount', 10, 2)->default(0.00)->after('service_fee');
            }
            if (!Schema::hasColumn('orders', 'tip_employee_id')) {
                $table->unsignedBigInteger('tip_employee_id')->nullable()->after('tip_amount');
            }
            if (!Schema::hasColumn('orders', 'tip_payout_mode')) {
                $table->string('tip_payout_mode', 30)->nullable()->after('tip_employee_id');
            }
            if (!Schema::hasColumn('orders', 'tip_settled_at')) {
                $table->timestamp('tip_settled_at')->nullable()->after('tip_payout_mode');
            }
        });

        if (Schema::hasTable('inventory_losses')) {
            // Permite perdas financeiras/fiscais sem produto físico vinculado
            DB::statement('ALTER TABLE inventory_losses MODIFY product_id BIGINT UNSIGNED NULL');

            Schema::table('inventory_losses', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_losses', 'order_id')) {
                    $table->unsignedBigInteger('order_id')->nullable()->after('product_id');
                }
                if (!Schema::hasColumn('inventory_losses', 'cash_shift_id')) {
                    $table->unsignedBigInteger('cash_shift_id')->nullable()->after('order_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['tip_settled_at', 'tip_payout_mode', 'tip_employee_id', 'tip_amount'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('inventory_losses')) {
            Schema::table('inventory_losses', function (Blueprint $table) {
                foreach (['cash_shift_id', 'order_id'] as $col) {
                    if (Schema::hasColumn('inventory_losses', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
