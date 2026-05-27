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
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        
        // Hierarquia (Auto-relacionamento)
        // Se a categoria pai for deletada, as filhas ficam "órfãs" (null) em vez de serem deletadas junto.
        $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();

        // Informações Básicas
        $table->string('name');
        $table->string('slug')->unique(); // URL amigável (ex: sucos-de-polpa)
        $table->text('description')->nullable();
        $table->string('image_path')->nullable();

        // Regras de Negócio (Status, Exposição e Ordem)
        $table->string('status')->default('available'); // available, unavailable, hidden
        $table->string('visibility')->default('external'); // external, internal, administrative
        $table->integer('sort_order')->default(0); // Para ordenar no cardápio: 1, 2, 3...

        $table->timestamps();
        $table->softDeletes(); // Histórico seguro
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
