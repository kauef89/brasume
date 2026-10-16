<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'status')) {
                $table->string('status', 30)->default('completed')->after('amount');
                $table->index(['order_id', 'status']);
                $table->index(['cash_shift_id', 'status']);
            }
        });

        // Backfill dos registros legados
        DB::table('payments')->where('is_paid', 1)->update(['status' => 'completed']);
        DB::table('payments')->where('is_paid', 0)->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'status')) {
                $table->dropIndex(['order_id', 'status']);
                $table->dropIndex(['cash_shift_id', 'status']);
                $table->dropColumn('status');
            }
        });
    }
};
