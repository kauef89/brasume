<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('loyalty_max_redeem_value', 10, 2)->nullable()->after('loyalty_min_redeem_value');
            $table->string('loyalty_max_redeem_type', 10)->default('fixed')->after('loyalty_max_redeem_value'); // 'fixed' (R$) ou 'percentage' (%)
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['loyalty_max_redeem_value', 'loyalty_max_redeem_type']);
        });
    }
};
