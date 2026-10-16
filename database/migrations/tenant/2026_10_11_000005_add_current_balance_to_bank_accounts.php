<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_accounts', 'current_balance')) {
                $table->decimal('current_balance', 15, 2)->default(0)->after('initial_balance');
            }
        });

        // Iguala o saldo atual ao inicial apenas para não começar zerado se já houver contas
        DB::statement("UPDATE bank_accounts SET current_balance = initial_balance");
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('current_balance');
        });
    }
};
