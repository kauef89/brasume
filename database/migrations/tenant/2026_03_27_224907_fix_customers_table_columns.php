<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Adiciona o CPF se não existir
            if (!Schema::hasColumn('customers', 'cpf')) {
                $table->string('cpf')->nullable()->after('name');
            }
            
            // Adiciona a Data de Nascimento se não existir
            if (!Schema::hasColumn('customers', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('cpf');
            }
            
            // Adiciona o Telefone se não existir
            if (!Schema::hasColumn('customers', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            
            // Adiciona suporte a Lixeira (Soft Deletes) se não existir
            if (!Schema::hasColumn('customers', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        // Não é necessário preencher o down neste caso de correção
    }
};