<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos Financeiros
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            
            // Detalhes do Movimento
            $table->string('type'); // 'in' (Entrada) ou 'out' (Saída)
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->string('operator_name')->nullable(); // Guardamos quem fez a operação no sistema antigo
            
            // Metadados do legado
            $table->string('legacy_session_id')->nullable(); 
            $table->timestamp('transaction_date');
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};