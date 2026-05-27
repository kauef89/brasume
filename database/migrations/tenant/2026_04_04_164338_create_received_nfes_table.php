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
    Schema::create('received_nfes', function (Blueprint $table) {
        $table->id();
        $table->string('nfe_key', 44)->unique(); // Chave de acesso
        $table->string('emitter_name');          // Razão Social do fornecedor
        $table->string('emitter_cnpj', 20);      // CNPJ do fornecedor
        $table->decimal('total_value', 15, 2);   // Valor total da nota
        $table->dateTime('issue_date');          // Data de emissão
        
        // Status: pending (pendente), manifested (manifestada), ready (pronta), processed (processada)
        $table->string('status')->default('pending'); 
        
        $table->longText('xml_content')->nullable(); // Guardaremos o XML aqui ou o path
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('received_nfes');
    }
};
