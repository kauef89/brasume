<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dbName = DB::getDatabaseName();

        // 1. Cria a coluna cashier_id na tabela payments
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'cashier_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('cashier_id')->nullable()->after('cash_shift_id');
            });
        }

        // 2. Cria a chave estrangeira segura para users.id
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'cashier_id')) {
            $fkExists = false;
            if (DB::getDriverName() !== 'sqlite') {
                $dbName = DB::getDatabaseName();
                $fkExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', $dbName)
                    ->where('TABLE_NAME', 'payments')
                    ->where('CONSTRAINT_NAME', 'payments_cashier_id_foreign')
                    ->exists();
            }

            if (!$fkExists && Schema::hasTable('users')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->index('cashier_id');
                    $table->foreign('cashier_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'cashier_id')) {
            Schema::table('payments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['cashier_id']);
                    $table->dropIndex(['cashier_id']);
                } catch (\Throwable $e) {}
                $table->dropColumn('cashier_id');
            });
        }
    }
};
