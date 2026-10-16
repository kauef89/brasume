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
        // 1. Limpeza na tabela pai
        Schema::table('checklists', function (Blueprint $table) {
            if (Schema::hasColumn('checklists', 'target_role')) {
                $table->dropColumn('target_role');
            }
        });

        // 2. Evolução na tabela filha (Tarefas)
        Schema::table('checklist_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('checklist_tasks', 'is_mandatory')) {
                $table->dropColumn('is_mandatory');
            }

            // Injetamos as novas colunas logo após a descrição para o banco ficar organizado
            $table->string('assignment_type')->default('role')->after('description');
            $table->string('target_role')->nullable()->after('assignment_type');

            $table->foreignId('target_user_id')
                  ->nullable()
                  ->after('target_role')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->string('completion_type')->default('shared')->after('target_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->dropForeign(['target_user_id']);
            $table->dropColumn(['assignment_type', 'target_role', 'target_user_id', 'completion_type']);

            $table->boolean('is_mandatory')->default(true);
        });

        Schema::table('checklists', function (Blueprint $table) {
            $table->string('target_role')->nullable();
        });
    }
};
