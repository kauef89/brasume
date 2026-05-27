<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            if (!Schema::hasColumn('neighborhoods', 'free_shipping_over')) {
                // Adicionamos a coluna permitindo valores nulos (se for null, não tem frete grátis)
                $table->decimal('free_shipping_over', 10, 2)->nullable()->after('delivery_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            if (Schema::hasColumn('neighborhoods', 'free_shipping_over')) {
                $table->dropColumn('free_shipping_over');
            }
        });
    }
};