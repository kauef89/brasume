<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Clientes: Só cria se NÃO existir
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('cpf')->unique();
                $table->date('birth_date');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Tabela de Endereços dos Clientes: Só cria se NÃO existir
        if (!Schema::hasTable('addresses')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customers');
    }
};