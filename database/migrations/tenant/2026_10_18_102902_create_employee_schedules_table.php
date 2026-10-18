<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('assigned_role');
            $table->boolean('is_extra_shift')->default(false);
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Evita duplicidade de escala para o mesmo funcionário no mesmo dia
            $table->unique(['employee_id', 'date']);

            // Relacionamento soft com account_payables
            $table->foreign('payable_id')->references('id')->on('account_payables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
