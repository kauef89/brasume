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
    Schema::create('bank_statements', function (Blueprint $table) {
        $table->id();
        
        // Dados brutos do banco (OFX)
        $table->string('fitid')->unique(); // O ID único da transação no banco (Evita duplicados)
        $table->date('bank_date'); // Data em que caiu na conta (<DTPOSTED>)
        $table->string('description'); // O histórico do banco (<MEMO>)
        $table->decimal('amount', 10, 2); // O valor (positivo ou negativo) (<TRNAMT>)
        $table->string('type'); // 'credit' ou 'debit'
        
        // Controle do Sistema
        $table->string('status')->default('pending'); // pending, reconciled, ignored
        
        // Relacionamento (A que transação final isto corresponde?)
        $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
