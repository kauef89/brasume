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
    Schema::table('modifier_options', function (Blueprint $table) {
        $table->decimal('linked_qty', 10, 4)->nullable()->after('linked_product_id')
              ->comment('Quantidade do insumo abatida a cada seleção desta opção');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_modifier_options', function (Blueprint $table) {
            //
        });
    }
};
