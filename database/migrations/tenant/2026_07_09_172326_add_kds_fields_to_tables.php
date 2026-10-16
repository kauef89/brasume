<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Regra de Negócio na Empresa
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('kds_access_mode')->default('role')->after('kitchen_separate_multiples')
                  ->comment('role = Acesso global por cargo | individual = Acesso restrito por praça/colaborador');
        });

        // 2. O Tipo de Praça na Cozinha
        Schema::table('kitchens', function (Blueprint $table) {
            $table->string('type')->default('kitchen')->after('name')
                  ->comment('kitchen = Kanban 3 colunas | bar = View rápida com Slide-to-confirm');
        });

        // 3. A Tabela Pivot para o modo de acesso individual
        Schema::create('employee_kitchen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained('kitchens')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_kitchen');

        Schema::table('kitchens', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('kds_access_mode');
        });
    }
};
