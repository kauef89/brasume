<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de Intenção de Compra (Single Source of Truth)
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Só é preenchido no momento em que o gerente clica em "Comprar deste fornecedor"
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('suggested_qty', 10, 3);
            $table->decimal('approved_qty', 10, 3)->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable(); // Baseado no último custo pago

            $table->string('status')->default('pending'); // pending, ordered, received, cancelled
            $table->string('urgency_level')->default('normal'); // normal, manual, critical (quando zera no PDV)

            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Tabela de Ordem de Produção (WIP - Cozinha)
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // Ex: Hambúrguer Artesanal
            $table->decimal('planned_qty', 10, 3);
            $table->decimal('actual_qty', 10, 3)->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed, cancelled
            $table->date('due_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('shopping_list_items');
    }
};
