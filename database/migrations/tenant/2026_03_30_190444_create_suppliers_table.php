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
    Schema::create('suppliers', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // nome_fornecedor
        $table->string('document')->nullable(); // documento_cnpj_cpf
        $table->string('pix_key')->nullable(); // chave_pix_principal
        $table->string('type')->default('physical'); // tipo_estabelecimento
        $table->string('accounting_category')->nullable(); // categoria_contabil
        $table->string('accounting_subcategory')->nullable(); // subcategoria_contabil
        $table->boolean('is_active')->default(true); // ativo_na_lista_compras
        $table->softDeletes();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
