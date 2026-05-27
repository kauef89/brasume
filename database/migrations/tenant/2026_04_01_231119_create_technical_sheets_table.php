<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('ingredient_product_id')->constrained('products')->restrictOnDelete();
            // 4 casas decimais para precisão (ex: 0.0050 kg)
            $table->decimal('quantity', 10, 4); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheets');
    }
};