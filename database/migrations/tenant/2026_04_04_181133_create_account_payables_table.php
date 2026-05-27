<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_payables', function (Blueprint $table) {
            $table->id();
            
            // Descrição e Fornecedor
            $table->string('description'); // Ex: Conta de Luz, Compra de Bebidas
            $table->string('supplier_name')->nullable(); // Nome do fornecedor (mais para a frente podemos ligar a uma tabela de Fornecedores)
            
            // Valores e Parcelas
            $table->decimal('amount', 15, 2); // Valor da conta
            $table->string('installment')->nullable(); // Para notas parceladas, ex: "1/3"
            
            // Datas
            $table->date('due_date'); // Data de vencimento original
            $table->date('payment_date')->nullable(); // Data em que o dinheiro realmente saiu
            
            // Controle de Status (pending, paid, cancelled)
            $table->string('status')->default('pending'); 
            
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};