<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // 🚀 Modificadores after() removidos para evitar erros de linha do tempo
            $table->string('checkout_print_mode')->default('ask');
            $table->string('receipt_footer_text')->nullable();
            $table->string('receipt_promo_qrcode')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['checkout_print_mode', 'receipt_footer_text', 'receipt_promo_qrcode']);
        });
    }
};
