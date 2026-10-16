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

        // 1. Assegura a existência e indexação de cashier_id em orders
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'cashier_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('cashier_id')->nullable()->after('employee_id');
            });
        }

        // 2. Chave estrangeira segura para employee_id
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'employee_id')) {
            $fkEmployeeExists = false;
            if (DB::getDriverName() !== 'sqlite') {
                $fkEmployeeExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', $dbName)
                    ->where('TABLE_NAME', 'orders')
                    ->where('CONSTRAINT_NAME', 'orders_employee_id_foreign')
                    ->exists();
            }

            if (!$fkEmployeeExists && Schema::hasTable('users')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->index('employee_id');
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            }
        }

        // 3. Chave estrangeira segura para cashier_id
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'cashier_id')) {
            $fkCashierExists = false;
            if (DB::getDriverName() !== 'sqlite') {
                $fkCashierExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', $dbName)
                    ->where('TABLE_NAME', 'orders')
                    ->where('CONSTRAINT_NAME', 'orders_cashier_id_foreign')
                    ->exists();
            }

            if (!$fkCashierExists && Schema::hasTable('users')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->index('cashier_id');
                    $table->foreign('cashier_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            }
        }

        // 4. Chave estrangeira segura em users.employee_id -> employees.id
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'employee_id')) {
            $fkUserEmpExists = false;
            if (DB::getDriverName() !== 'sqlite') {
                $fkUserEmpExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', $dbName)
                    ->where('TABLE_NAME', 'users')
                    ->where('CONSTRAINT_NAME', 'users_employee_id_foreign')
                    ->exists();
            }

            if (!$fkUserEmpExists && Schema::hasTable('employees')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->index('employee_id');
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employees')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'cashier_id')) {
            Schema::table('orders', function (Blueprint $table) {
                try {
                    $table->dropForeign(['cashier_id']);
                    $table->dropIndex(['cashier_id']);
                } catch (\Throwable $e) {}
            });
        }
    }
};
