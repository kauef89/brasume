<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adiciona os dados bancários
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('name');
            $table->string('agency')->nullable()->after('bank_name');
            $table->string('account_number')->nullable()->after('agency');
        });

        // Remove o ícone das formas de pagamento
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'agency', 'account_number']);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('icon')->nullable();
        });
    }
};