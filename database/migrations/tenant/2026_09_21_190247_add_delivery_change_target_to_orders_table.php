<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_change_target')) {
                $table->decimal('delivery_change_target', 10, 2)
                      ->nullable()
                      ->after('payment_intent_status')
                      ->comment('Valor alvo para cálculo de troco solicitado pelo cliente');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_change_target')) {
                $table->dropColumn('delivery_change_target');
            }
        });
    }
};
