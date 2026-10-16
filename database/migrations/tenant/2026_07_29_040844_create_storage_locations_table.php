<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();

            // Se você usar Tenancy Single-Database (stancl/tenancy v4), descomente a linha abaixo:
            // $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // Ex: "Cervejeira 1", "Câmara Fria Bebidas", "Estoque Seco"
            $table->enum('type', ['display', 'intermediate', 'storage'])->default('storage');
            $table->text('description')->nullable();

            $table->integer('sort_order')->default(0); // Define o trajeto físico do garçom
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_locations');
    }
};
