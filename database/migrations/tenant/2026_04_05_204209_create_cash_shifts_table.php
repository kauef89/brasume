<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            
            // Qual "Gaveta" (Cofre) está a ser usada?
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            
            // Quem abriu e quem fechou o caixa?
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Datas e Horas
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            
            // Valores Financeiros (Apenas Dinheiro Físico da Gaveta)
            $table->decimal('opening_balance', 10, 2)->default(0); // Fundo de Troco
            $table->decimal('closing_balance_expected', 10, 2)->nullable(); // Calculado pelo sistema
            $table->decimal('closing_balance_actual', 10, 2)->nullable(); // Contado pelo Operador
            
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('notes')->nullable(); // Justificativa de Quebra de Caixa
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_shifts');
    }
};