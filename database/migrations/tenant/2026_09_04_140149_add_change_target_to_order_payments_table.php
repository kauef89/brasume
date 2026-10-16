<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            // Adiciona a coluna permitindo nulos (pois PIX e Cartão não têm troco)
            $table->decimal('change_target', 10, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('change_target');
        });
    }
};
