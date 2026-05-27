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
    Schema::create('order_item_modifiers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
        $table->foreignId('modifier_option_id')->nullable()->constrained()->nullOnDelete();
        
        $table->string('name'); // Guardamos o nome caso a opção original seja alterada no futuro
        $table->integer('quantity')->default(1);
        $table->decimal('price', 10, 2)->default(0); // Preço cobrado na hora
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
