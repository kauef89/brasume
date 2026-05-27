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
        Schema::table('product_cost_histories', function (Blueprint $table) {
            // Adicionamos as colunas caso elas não existam (segurança em primeiro lugar!)
            if (!Schema::hasColumn('product_cost_histories', 'original_quantity')) {
                $table->decimal('original_quantity', 10, 3)->nullable()->after('conversion_factor');
            }
            if (!Schema::hasColumn('product_cost_histories', 'original_unit_cost')) {
                $table->decimal('original_unit_cost', 10, 4)->nullable()->after('original_quantity');
            }
            if (!Schema::hasColumn('product_cost_histories', 'original_unit_of_measure')) {
                $table->string('original_unit_of_measure')->nullable()->after('original_unit_cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_cost_histories', function (Blueprint $table) {
            $table->dropColumn([
                'original_quantity',
                'original_unit_cost',
                'original_unit_of_measure'
            ]);
        });
    }
};