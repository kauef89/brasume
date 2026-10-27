<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bank_statements', 'match_score')) {
            Schema::table('bank_statements', function (Blueprint $table) {
                $table->integer('match_score')->default(0)->after('status')->index();
                $table->foreignId('suggested_payable_id')->nullable()->after('match_score')->constrained('account_payables')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_statements', 'match_score')) {
            Schema::table('bank_statements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('suggested_payable_id');
                $table->dropColumn('match_score');
            });
        }
    }
};
