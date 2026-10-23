<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fiscal_status', 30)
                ->nullable()
                ->default(null)
                ->index()
                ->after('status')
                ->comment('Status fiscal: pending, authorized, failed, skipped');

            $table->text('fiscal_error')
                ->nullable()
                ->after('fiscal_status')
                ->comment('Última mensagem de erro ou rejeição da SEFAZ');
        });

        // 🚀 Sincroniza retroativamente os pedidos que já possuem NFC-e autorizada
        if (Schema::hasTable('fiscal_documents')) {
            DB::statement("
                UPDATE orders o
                INNER JOIN fiscal_documents fd ON fd.order_id = o.id
                SET o.fiscal_status = 'authorized'
                WHERE fd.status = 'authorized'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fiscal_status', 'fiscal_error']);
        });
    }
};
