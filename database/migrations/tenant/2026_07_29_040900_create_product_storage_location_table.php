<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_storage_location', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // O espaço onde o produto está (Destino da movimentação)
            $table->foreignId('storage_location_id')->constrained()->cascadeOnDelete();

            // O espaço de onde o produto deve ser PUXADO (A -> B). Se for nulo, é o início da cadeia.
            $table->foreignId('source_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();

            // Par Level (Req 1): Quantos itens cabem neste espaço para ficar "cheio"
            $table->integer('max_capacity')->default(0);

            // Opcional: Ponto de reabastecimento (Ex: Avisar quando chegar a 5, antes de zerar)
            $table->integer('restock_threshold')->default(0);

            $table->timestamps();

            // Garante que o mesmo produto não tenha regras duplicadas no mesmo espaço
            $table->unique(['product_id', 'storage_location_id'], 'prod_loc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_storage_location');
    }
};
