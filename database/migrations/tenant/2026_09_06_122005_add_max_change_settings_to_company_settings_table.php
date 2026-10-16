<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('has_max_change_limit')->default(false)->after('out_of_stock_mode');
            $table->decimal('max_change_amount', 10, 2)->nullable()->after('has_max_change_limit');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['has_max_change_limit', 'max_change_amount']);
        });
    }
};
