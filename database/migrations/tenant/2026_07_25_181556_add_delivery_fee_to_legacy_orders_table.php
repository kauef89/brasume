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
        Schema::table('legacy_orders', function (Blueprint $table) {
            // Adiciona apenas a coluna que realmente falta, com o tamanho correto (12,2)
            if (!Schema::hasColumn('legacy_orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 12, 2)->default(0)->after('total_value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legacy_orders', function (Blueprint $table) {
            if (Schema::hasColumn('legacy_orders', 'delivery_fee')) {
                $table->dropColumn('delivery_fee');
            }
        });
    }
};
