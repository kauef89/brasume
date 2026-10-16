<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renomeamos a tabela principal
        Schema::rename('fixed_accounts', 'recurring_expenses');

        // 2. Atualizamos a chave estrangeira nas Contas a Pagar
        Schema::table('account_payables', function (Blueprint $table) {
            // Nota: Se você tiver restrições de chave estrangeira (foreign keys) rígidas no banco,
            // o Laravel pode exigir que você faça o drop constraint antes de renomear.
            // Ex: $table->dropForeign(['fixed_account_id']);

            $table->renameColumn('fixed_account_id', 'recurring_expense_id');

            // Ex: $table->foreign('recurring_expense_id')->references('id')->on('recurring_expenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // O caminho inverso caso precisemos de fazer um rollback
        Schema::table('account_payables', function (Blueprint $table) {
            $table->renameColumn('recurring_expense_id', 'fixed_account_id');
        });

        Schema::rename('recurring_expenses', 'fixed_accounts');
    }
};
