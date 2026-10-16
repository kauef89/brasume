<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            if (!Schema::hasColumn('account_payables', 'bank_account_id')) {
                $table->foreignId('bank_account_id')->nullable()->after('employee_id')->constrained('bank_accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
    }
};
