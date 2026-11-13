<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('bank_statements')) {
            Schema::table('bank_statements', function (Blueprint $table) {
                if (!Schema::hasColumn('bank_statements', 'reconciliation_source')) {
                    $table->string('reconciliation_source', 20)->nullable()->after('status');
                }
                if (!Schema::hasColumn('bank_statements', 'reconciled_by_name')) {
                    $table->string('reconciled_by_name', 120)->nullable()->after('reconciliation_source');
                }
                if (!Schema::hasColumn('bank_statements', 'reconciled_at')) {
                    $table->timestamp('reconciled_at')->nullable()->after('reconciled_by_name');
                }
            });
        }

        if (Schema::hasTable('acquirer_transactions')) {
            Schema::table('acquirer_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('acquirer_transactions', 'reconciliation_source')) {
                    $table->string('reconciliation_source', 20)->nullable()->after('status');
                }
                if (!Schema::hasColumn('acquirer_transactions', 'reconciled_by_name')) {
                    $table->string('reconciled_by_name', 120)->nullable()->after('reconciliation_source');
                }
                if (!Schema::hasColumn('acquirer_transactions', 'reconciled_at')) {
                    $table->timestamp('reconciled_at')->nullable()->after('reconciled_by_name');
                }
            });
        }
    }

    public function down(): void {}
};