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
    Schema::create('combo_groups', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->constrained('products')->cascadeOnDelete(); // O Produto "Pai" (O Combo)
        $table->string('name'); // Ex: Escolha sua Bebida
        $table->integer('min_choices')->default(1);
        $table->integer('max_choices')->default(1);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combo_groups');
    }
};
