<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unsignedInteger('corrected_errors_count')->default(0)->after('closing_balance_actual');
            $table->decimal('corrected_errors_amount', 10, 2)->default(0.00)->after('corrected_errors_count');
            $table->json('corrected_errors_details')->nullable()->after('corrected_errors_amount');
            $table->decimal('cash_breakage_amount', 10, 2)->default(0.00)->after('corrected_errors_details');
            $table->decimal('apos_breakage_amount', 10, 2)->default(0.00)->after('cash_breakage_amount');
            $table->boolean('has_breakage')->default(false)->after('apos_breakage_amount');
        });
    }

    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'corrected_errors_count',
                'corrected_errors_amount',
                'corrected_errors_details',
                'cash_breakage_amount',
                'apos_breakage_amount',
                'has_breakage',
            ]);
        });
    }
};
