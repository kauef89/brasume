<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('street_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('street_id')->constrained('streets')->cascadeOnDelete();
            $table->foreignId('neighborhood_id')->constrained('neighborhoods')->cascadeOnDelete();
            
            $table->integer('start_number')->default(0);
            $table->integer('end_number')->nullable(); // NULL = até o infinito
            $table->string('zip_code')->nullable(); // Caso o CEP mude na faixa
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('street_segments');
    }
};