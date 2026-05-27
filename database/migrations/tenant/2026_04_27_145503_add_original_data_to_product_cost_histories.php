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
        // O custo que veio na nota (sem conversão)
        $table->decimal('original_unit_cost', 15, 5)->nullable()->after('unit_cost');
        // A unidade que veio na nota (ex: CX, FD, UN)
        $table->string('original_unit_of_measure')->nullable()->after('conversion_factor');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_cost_histories', function (Blueprint $table) {
            //
        });
    }
};
