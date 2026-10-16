<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Primeiro cria a coluna fisicamente
        Schema::table('goods_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('goods_receipts', 'requires_nfe')) {
                $table->boolean('requires_nfe')->default(true)->after('receipt_number');
            }
        });

        // 2. DEPOIS atualiza os dados
        DB::statement("UPDATE goods_receipts SET requires_nfe = 1, payment_origin = 'payable' WHERE payment_origin = 'pending_invoice'");
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('goods_receipts', 'requires_nfe')) {
                $table->dropColumn('requires_nfe');
            }
        });
    }
};
