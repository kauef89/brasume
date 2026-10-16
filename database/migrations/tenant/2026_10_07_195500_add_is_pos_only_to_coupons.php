<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coupons') && !Schema::hasColumn('coupons', 'is_pos_only')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->boolean('is_pos_only')->default(false)->after('first_order_only');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'is_pos_only')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('is_pos_only');
            });
        }
    }
};
