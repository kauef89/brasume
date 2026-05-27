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
        // 1. Desliga as travas de chaves estrangeiras temporariamente
        Schema::disableForeignKeyConstraints();
        
        // 2. Destrói a tabela velha se ela existir
        Schema::dropIfExists('dining_tables');
        
        // 3. Religa as travas de segurança
        Schema::enableForeignKeyConstraints();

        // 4. Cria a tabela nova e limpa
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_area_id')->constrained()->cascadeOnDelete();
            $table->string('name'); 
            $table->string('status')->default('available'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
