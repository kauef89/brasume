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
    Schema::create('order_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // nullOnDelete protege o histórico se o produto for apagado
        
        $table->integer('quantity')->default(1);
        $table->decimal('unit_price', 10, 2); // Cópia exata do preço no momento da venda
        $table->decimal('total_price', 10, 2); 
        
        $table->boolean('is_to_go')->default(false); // Para levar (O embrulho extra que mencionou!)
        $table->text('notes')->nullable(); // Ex: "Bem passado"
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_modifiers');
    }
};
