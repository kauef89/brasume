<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('company_settings', 'team_order_channels')) {
                    $table->json('team_order_channels')->nullable()->after('customer_can_append_orders');
                }
                if (!Schema::hasColumn('company_settings', 'team_counter_mode')) {
                    $table->string('team_counter_mode', 30)->default('simple')->after('team_order_channels');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                if (Schema::hasColumn('company_settings', 'team_counter_mode')) {
                    $table->dropColumn('team_counter_mode');
                }
                if (Schema::hasColumn('company_settings', 'team_order_channels')) {
                    $table->dropColumn('team_order_channels');
                }
            });
        }
    }
};