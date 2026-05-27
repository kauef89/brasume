<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // 'total' = Digita apenas R$ 150,00 | 'detailed' = Conta 2 notas de 50, 5 notas de 10...
            $table->string('cash_opening_mode')->default('total')->after('has_service_tax');

            // 'none' = Zera todo dia | 'total' = Puxa o saldo total final | 'coins_only' = Puxa apenas moedas/troco
            $table->string('carry_over_mode')->default('none')->after('cash_opening_mode');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['cash_opening_mode', 'carry_over_mode']);
        });
    }
};
