<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_vacations', function (Blueprint $table) {
            $table->id();
            
            // Vínculo com o funcionário
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            
            $table->date('start_date');
            $table->date('end_date');
            $table->string('description'); // Ex: "Férias 2026", "Licença Maternidade"
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_vacations');
    }
};