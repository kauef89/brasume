<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('auto_fiscal_emission')
                ->default(true)
                ->after('is_active')
                ->comment('Determina se vendas com esta forma tentam emitir NFC-e imediatamente');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('auto_fiscal_emission');
        });
    }
};
