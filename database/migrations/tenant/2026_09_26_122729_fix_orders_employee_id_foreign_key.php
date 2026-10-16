<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove a constraint antiga caso ela ainda exista
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });
        } catch (\Throwable $e) {
            // Ignora caso a constraint já tenha caído na tentativa anterior
        }

        // 2. HIGIENIZAÇÃO: Limpa IDs antigos que não existem na tabela users
        DB::statement("
            UPDATE orders
            SET employee_id = NULL
            WHERE employee_id IS NOT NULL
              AND employee_id NOT IN (SELECT id FROM users)
        ");

        // 3. Recria a foreign key apontando para users
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('employee_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }
};
