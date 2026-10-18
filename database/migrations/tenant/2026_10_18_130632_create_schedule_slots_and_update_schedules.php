<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Slots customizados pelo Gestor
        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // Ex: Cozinha Noite, Salão, Balcão
            $table->unsignedInteger('capacity')->default(1); // Quantidade de vagas
            $table->string('color')->nullable(); // Cor de identificação
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Atualização na tabela de escalas
        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->foreignId('schedule_slot_id')->nullable()->constrained('schedule_slots')->nullOnDelete();
            $table->boolean('is_day_off')->default(false); // Flag para Folga Programada
        });
    }

    public function down(): void
    {
        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->dropForeign(['schedule_slot_id']);
            $table->dropColumn(['schedule_slot_id', 'is_day_off']);
        });
        Schema::dropIfExists('schedule_slots');
    }
};
