<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Desliga temporariamente a verificação de chaves estrangeiras para podermos apagar sem erros
        Schema::disableForeignKeyConstraints();
        
        // 2. O Trator: Apaga as tabelas defeituosas se elas existirem
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customers');
        
        // 3. Liga as verificações de volta
        Schema::enableForeignKeyConstraints();

        // 4. Cria a tabela de Clientes PERFEITA
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cpf')->unique(); // O famoso CPF que estava faltando!
            $table->date('birth_date');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Cria a tabela de Endereços PERFEITA
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('street_id')->constrained('streets')->restrictOnDelete();
            $table->foreignId('neighborhood_id')->constrained('neighborhoods')->restrictOnDelete();
            $table->string('number');
            $table->string('complement')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customers');
    }
};