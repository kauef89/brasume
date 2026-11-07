<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_statements', 'suggested_receivable_id')) {
                $table->unsignedBigInteger('suggested_receivable_id')->nullable()->after('suggested_payable_id');
            }
            if (!Schema::hasColumn('bank_statements', 'suggested_match_type')) {
                $table->string('suggested_match_type')->nullable()->after('match_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            if (Schema::hasColumn('bank_statements', 'suggested_receivable_id')) {
                $table->dropColumn('suggested_receivable_id');
            }
            if (Schema::hasColumn('bank_statements', 'suggested_match_type')) {
                $table->dropColumn('suggested_match_type');
            }
        });
    }
};