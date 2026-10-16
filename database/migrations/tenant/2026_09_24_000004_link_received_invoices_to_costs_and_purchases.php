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

        // 1. Adiciona received_invoice_id em product_cost_histories
        if (Schema::hasTable('product_cost_histories') && !Schema::hasColumn('product_cost_histories', 'received_invoice_id')) {
            Schema::table('product_cost_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('received_invoice_id')->nullable()->after('supplier_id');
            });
        }

        // 2. Adiciona received_invoice_id em personal_purchases
        if (Schema::hasTable('personal_purchases') && !Schema::hasColumn('personal_purchases', 'received_invoice_id')) {
            Schema::table('personal_purchases', function (Blueprint $table) {
                $table->unsignedBigInteger('received_invoice_id')->nullable()->after('supplier_id');
            });
        }

        // 3. Backfill relacional cruzando a chave da NFe (apenas MySQL/MariaDB)
        if (!$isSqlite && Schema::hasTable('received_invoices')) {
            if (Schema::hasTable('product_cost_histories') && Schema::hasColumn('product_cost_histories', 'nfe_key')) {
                DB::statement('
                    UPDATE product_cost_histories pch
                    INNER JOIN received_invoices ri ON ri.nfe_key = pch.nfe_key
                    SET pch.received_invoice_id = ri.id
                    WHERE pch.received_invoice_id IS NULL AND pch.nfe_key IS NOT NULL
                ');
            }

            if (Schema::hasTable('personal_purchases') && Schema::hasColumn('personal_purchases', 'nfe_key')) {
                DB::statement('
                    UPDATE personal_purchases pp
                    INNER JOIN received_invoices ri ON ri.nfe_key = pp.nfe_key
                    SET pp.received_invoice_id = ri.id
                    WHERE pp.received_invoice_id IS NULL AND pp.nfe_key IS NOT NULL
                ');
            }
        }

        // 4. Constraint segura para product_cost_histories
        if (Schema::hasTable('product_cost_histories') && Schema::hasColumn('product_cost_histories', 'received_invoice_id')) {
            $fkPchExists = false;

            if (!$isSqlite) {
                $fkPchExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                    ->where('TABLE_NAME', 'product_cost_histories')
                    ->where('CONSTRAINT_NAME', 'pch_received_invoice_id_foreign')
                    ->exists();
            }

            if (!$fkPchExists && Schema::hasTable('received_invoices')) {
                Schema::table('product_cost_histories', function (Blueprint $table) {
                    $table->index('received_invoice_id', 'pch_received_invoice_id_index');
                    $table->foreign('received_invoice_id', 'pch_received_invoice_id_foreign')
                        ->references('id')
                        ->on('received_invoices')
                        ->nullOnDelete();
                });
            }
        }

        // 5. Constraint segura para personal_purchases
        if (Schema::hasTable('personal_purchases') && Schema::hasColumn('personal_purchases', 'received_invoice_id')) {
            $fkPpExists = false;

            if (!$isSqlite) {
                $fkPpExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                    ->where('TABLE_NAME', 'personal_purchases')
                    ->where('CONSTRAINT_NAME', 'pp_received_invoice_id_foreign')
                    ->exists();
            }

            if (!$fkPpExists && Schema::hasTable('received_invoices')) {
                Schema::table('personal_purchases', function (Blueprint $table) {
                    $table->index('received_invoice_id', 'pp_received_invoice_id_index');
                    $table->foreign('received_invoice_id', 'pp_received_invoice_id_foreign')
                        ->references('id')
                        ->on('received_invoices')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_cost_histories') && Schema::hasColumn('product_cost_histories', 'received_invoice_id')) {
            Schema::table('product_cost_histories', function (Blueprint $table) {
                try {
                    $table->dropForeign('pch_received_invoice_id_foreign');
                    $table->dropIndex('pch_received_invoice_id_index');
                } catch (\Throwable $e) {}
                $table->dropColumn('received_invoice_id');
            });
        }

        if (Schema::hasTable('personal_purchases') && Schema::hasColumn('personal_purchases', 'received_invoice_id')) {
            Schema::table('personal_purchases', function (Blueprint $table) {
                try {
                    $table->dropForeign('pp_received_invoice_id_foreign');
                    $table->dropIndex('pp_received_invoice_id_index');
                } catch (\Throwable $e) {}
                $table->dropColumn('received_invoice_id');
            });
        }
    }
};
