<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('company_settings', 'loyalty_points_expiry_days')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->integer('loyalty_points_expiry_days')->nullable()->after('loyalty_max_redeem_type');
            });
        }

        if (!Schema::hasColumn('customers', 'loyalty_points_expires_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('loyalty_points_expires_at')->nullable()->after('loyalty_points');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_settings', 'loyalty_points_expiry_days')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('loyalty_points_expiry_days');
            });
        }

        if (Schema::hasColumn('customers', 'loyalty_points_expires_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('loyalty_points_expires_at');
            });
        }
    }
};
