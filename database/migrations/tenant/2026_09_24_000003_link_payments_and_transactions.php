<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        // 1. Assegura a existência da coluna payment_id em transactions
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'payment_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_id')->nullable()->after('id');
            });
        }

        // 2. Assegura a existência da coluna cash_shift_id em payments
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'cash_shift_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('cash_shift_id')->nullable()->after('order_id');
            });
        }

        // Backfills de Produção (Apenas MySQL)
        if (!$isSqlite && Schema::hasTable('transactions') && Schema::hasTable('payments')) {
            // 3. Backfill relacional: liga a transação ao pagamento via order_id + valor
            if (Schema::hasColumn('transactions', 'payment_id')) {
                DB::statement('
                    UPDATE transactions t
                    INNER JOIN payments p ON p.order_id = t.order_id AND ABS(p.amount - t.amount) < 0.001
                    SET t.payment_id = p.id
                    WHERE t.payment_id IS NULL AND t.order_id IS NOT NULL 
                ');
            }

            // 4. Backfill de turno em payments a partir da transação correspondente
            if (Schema::hasColumn('payments', 'cash_shift_id') && Schema::hasColumn('transactions', 'payment_id')) {
                DB::statement('
                    UPDATE payments p
                    INNER JOIN transactions t ON t.payment_id = p.id
                    SET p.cash_shift_id = t.cash_shift_id
                    WHERE p.cash_shift_id IS NULL AND t.cash_shift_id IS NOT NULL
                ');
            }
        }

        // 5. Inspeciona se a Foreign Key de payment_id já existe antes de criá-la
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'payment_id')) {
            $fkExists = false;

            if (!$isSqlite) {
                $fkExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                    ->where('TABLE_NAME', 'transactions')
                    ->where('CONSTRAINT_NAME', 'transactions_payment_id_foreign')
                    ->exists();
            }

            if (!$fkExists && Schema::hasTable('payments')) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->index('payment_id');
                    $table->foreign('payment_id')
                        ->references('id')
                        ->on('payments')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'payment_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                try {
                    $table->dropForeign('transactions_payment_id_foreign');
                    $table->dropIndex('transactions_payment_id_index');
                } catch (\Throwable $e) {}

                $table->dropColumn('payment_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'cash_shift_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('cash_shift_id');
            });
        }
    }
};
