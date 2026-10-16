<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Limpar a tabela Pai (Checklists) com segurança
        Schema::table('checklists', function (Blueprint $table) {
            $columnsToDrop = [
                'target_role', 'trigger_type', 'trigger_time',
                'recurrence_days', 'assignment_type', 'target_user_id',
                'completion_type'
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('checklists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // 2. Dar inteligência à tabela Filha (Checklist_Tasks) APENAS se a coluna não existir
        Schema::table('checklist_tasks', function (Blueprint $table) {

            if (Schema::hasColumn('checklist_tasks', 'is_mandatory')) {
                $table->dropColumn('is_mandatory');
            }

            // --- Bloco de Atribuições ---
            if (!Schema::hasColumn('checklist_tasks', 'assignment_type')) {
                $table->string('assignment_type')->default('all')->after('description');
            }
            if (!Schema::hasColumn('checklist_tasks', 'target_roles')) {
                $table->json('target_roles')->nullable()->after('assignment_type');
            }
            if (!Schema::hasColumn('checklist_tasks', 'target_users')) {
                $table->json('target_users')->nullable()->after('target_roles');
            }
            if (!Schema::hasColumn('checklist_tasks', 'completion_type')) {
                $table->string('completion_type')->default('shared')->after('target_users');
            }

            // --- Bloco de Disparos e Recorrência ---
            if (!Schema::hasColumn('checklist_tasks', 'trigger_type')) {
                $table->string('trigger_type')->default('manual')->after('completion_type');
            }
            if (!Schema::hasColumn('checklist_tasks', 'trigger_time')) {
                $table->time('trigger_time')->nullable()->after('trigger_type');
            }
            if (!Schema::hasColumn('checklist_tasks', 'recurrence_type')) {
                $table->string('recurrence_type')->default('daily')->after('trigger_time');
            }
            if (!Schema::hasColumn('checklist_tasks', 'recurrence_days')) {
                $table->json('recurrence_days')->nullable()->after('recurrence_type');
            }

            // --- Bloco de Deadlines (Prazos) ---
            if (!Schema::hasColumn('checklist_tasks', 'deadline_minutes')) {
                $table->integer('deadline_minutes')->default(60)->nullable()->after('recurrence_days')->comment('Minutos de tolerância após o gatilho');
            }
            if (!Schema::hasColumn('checklist_tasks', 'deadline_date')) {
                $table->dateTime('deadline_date')->nullable()->after('deadline_minutes')->comment('Deadline absoluto para tarefas pontuais');
            }
        });
    }

    public function down()
    {
        // Num ambiente de produção real, o down faria o drop das colunas criadas acima.
    }
};
