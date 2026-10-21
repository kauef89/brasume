<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('asset_tag')->nullable()->unique()->comment('Plaqueta / Código de Tombamento');
            $table->string('location')->nullable()->comment('Local físico: Fachada, Cozinha, Salão, Escritório');
            $table->text('description')->nullable();

            // Dados Financeiros & Aquisição
            $table->decimal('acquisition_cost', 12, 2)->default(0);
            $table->date('acquisition_date')->nullable();
            $table->date('warranty_until')->nullable();

            // Rastreabilidade de Compra / Fornecedor
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('nfe_key', 44)->nullable()->index()->comment('Chave da NF-e de compra');

            // Estado do Bem
            $table->string('status')->default('active')->comment('active, in_maintenance, written_off');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_assets');
    }
};
