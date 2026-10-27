<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('account_payables', 'transaction_id')) {
            Schema::table('account_payables', function (Blueprint $table) {
                $table->foreignId('transaction_id')->nullable()->after('bank_account_id')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('account_receivables', 'transaction_id')) {
            Schema::table('account_receivables', function (Blueprint $table) {
                $table->foreignId('transaction_id')->nullable()->after('bank_account_id')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('account_payables', 'transaction_id')) {
            Schema::table('account_payables', function (Blueprint $table) {
                $table->dropConstrainedForeignId('transaction_id');
            });
        }

        if (Schema::hasColumn('account_receivables', 'transaction_id')) {
            Schema::table('account_receivables', function (Blueprint $table) {
                $table->dropConstrainedForeignId('transaction_id');
            });
        }
    }
};
