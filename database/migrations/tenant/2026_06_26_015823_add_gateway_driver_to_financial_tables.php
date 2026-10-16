<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Adicionamos o Motor da API aos Meios de Pagamento
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('gateway_driver')->nullable()->after('engine_type');
        });

        // 2. Garantimos que a Conta Bancária sabe de que Banco ela é
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_accounts', 'bank_code')) {
                $table->string('bank_code', 10)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('gateway_driver');
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('bank_accounts', 'bank_code')) {
                $table->dropColumn('bank_code');
            }
        });
    }
};
