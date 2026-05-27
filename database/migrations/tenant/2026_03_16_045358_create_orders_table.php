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
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->string('type'); // table, counter, delivery
        
        // Identificação (Tudo anulável pois depende do tipo de pedido)
        $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
        $table->string('tab_name')->nullable(); // Nome ou Número da Comanda
        
        // A "Fotografia" do Delivery
        $table->json('delivery_address')->nullable(); 
        
        // Os 3 Níveis de Status
        $table->string('status')->default('open'); // open, closed, canceled
        $table->string('fulfillment_status')->default('pending'); // pending, preparing, ready, dispatched, delivered
        $table->string('payment_status')->default('pending'); // pending, partial, paid
        
        // Totais Financeiros
        $table->decimal('subtotal', 10, 2)->default(0);
        $table->decimal('discount', 10, 2)->default(0);
        $table->decimal('delivery_fee', 10, 2)->default(0);
        $table->decimal('total', 10, 2)->default(0);
        
        $table->boolean('is_takeaway')->default(false); // Se o pedido inteiro for para levar
        $table->text('notes')->nullable(); // Observações gerais do pedido

        $table->timestamps();
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
