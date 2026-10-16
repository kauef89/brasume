<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('company_settings', 'customer_can_append_orders')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->boolean('customer_can_append_orders')
                    ->default(true)
                    ->after('out_of_stock_mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_settings', 'customer_can_append_orders')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('customer_can_append_orders');
            });
        }
    }
};
