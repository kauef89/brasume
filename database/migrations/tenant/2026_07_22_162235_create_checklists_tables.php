<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. O Cabeçalho (A rotina em si)
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('target_role')->nullable()->comment('null = todos, kitchen, waiter, cashier, driver');
            $table->string('trigger_type')->default('shift_start')->comment('shift_start, shift_end, scheduled, manual');
            $table->time('trigger_time')->nullable()->comment('Hora exata caso seja scheduled');
            $table->json('recurrence_days')->nullable()->comment('Dias da semana [0,1,2...]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. As Tarefas
        Schema::create('checklist_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->boolean('is_mandatory')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. O Diário de Bordo (O que foi executado)
        Schema::create('checklist_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('done')->comment('done, issue');
            $table->text('notes')->nullable(); // Para quando houver 'issue'
            $table->timestamps();

            // Impede que a mesma tarefa seja marcada duas vezes no mesmo turno
            $table->unique(['checklist_task_id', 'cash_shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_logs');
        Schema::dropIfExists('checklist_tasks');
        Schema::dropIfExists('checklists');
    }
};
