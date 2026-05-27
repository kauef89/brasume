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
        Schema::create('personal_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('nfe_key')->index(); // Chave da nota legada
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('xml_name'); // O nome que veio na nota
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_cost', 10, 4);
            $table->decimal('total_cost', 10, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_purchases');
    }
};
