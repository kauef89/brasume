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
       Schema::table('bank_accounts', function (Blueprint $table) {
    // Taxas (em percentual)
    $table->decimal('debit_fee', 5, 2)->default(0);
    $table->decimal('credit_fee', 5, 2)->default(0);
    $table->decimal('anticipation_fee', 5, 2)->default(0);
    
    // Prazos (em dias)
    $table->integer('debit_term_days')->default(1); // Geralmente D+1
    $table->integer('credit_term_days')->default(30); // Geralmente D+30
    
    // Configuração de Antecipação
    $table->string('anticipation_strategy')->default('none'); // none, automatic, manual
    $table->json('anticipation_days')->nullable(); // Para guardar ["monday", "wednesday"]
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            //
        });
    }
};
