<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bank_accounts', 'is_personal')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->boolean('is_personal')->default(false)->after('type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_accounts', 'is_personal')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->dropColumn('is_personal');
            });
        }
    }
};