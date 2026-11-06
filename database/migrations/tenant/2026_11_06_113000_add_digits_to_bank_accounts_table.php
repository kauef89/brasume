<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_accounts', 'agency_digit')) {
                $table->string('agency_digit', 2)->nullable()->after('agency');
            }
            if (!Schema::hasColumn('bank_accounts', 'account_digit')) {
                $table->string('account_digit', 2)->nullable()->after('account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['agency_digit', 'account_digit']);
        });
    }
};
