<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('enforce_cashier_open_customer')->default(true)->after('enforce_cashier_open');
            $table->boolean('enforce_cashier_open_team')->default(false)->after('enforce_cashier_open_customer');
        });

        // 🚀 Migra a preferência existente do tenant
        DB::table('company_settings')->update([
            'enforce_cashier_open_customer' => DB::raw('COALESCE(enforce_cashier_open, 1)'),
            'enforce_cashier_open_team'     => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['enforce_cashier_open_customer', 'enforce_cashier_open_team']);
        });
    }
};
