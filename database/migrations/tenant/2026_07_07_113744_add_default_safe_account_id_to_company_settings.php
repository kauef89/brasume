<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->foreignId('default_safe_account_id')
                  ->nullable()
                  ->constrained('bank_accounts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropForeign(['default_safe_account_id']);
            $table->dropColumn('default_safe_account_id');
        });
    }
};
