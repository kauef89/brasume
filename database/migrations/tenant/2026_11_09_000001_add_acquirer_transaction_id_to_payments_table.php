<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'acquirer_transaction_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('acquirer_transaction_id')
                    ->nullable()
                    ->after('transaction_id')
                    ->constrained('acquirer_transactions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'acquirer_transaction_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['acquirer_transaction_id']);
                $table->dropColumn('acquirer_transaction_id');
            });
        }
    }
};