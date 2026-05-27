<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // 4 casas decimais para podermos guardar "R$ 0,005 por grama"
            $table->decimal('cost', 10, 4); 
            $table->date('reference_date');
            $table->string('source')->nullable(); // Ex: 'manual', 'purchase', 'recipe_calc'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_histories');
    }
};