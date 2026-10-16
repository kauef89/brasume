<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Adiciona a coluna para guardar o ID exato do método de pagamento
            $table->unsignedBigInteger('payment_method_id')->nullable()->after('order_id');

            // Cria o vínculo (Chave Estrangeira) para integridade dos dados
            $table->foreign('payment_method_id')
                  ->references('id')->on('payment_methods')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
        });
    }
};
