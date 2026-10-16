<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Adiciona a coluna address_snapshot caso ainda não exista
        if (!Schema::hasColumn('orders', 'address_snapshot')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->json('address_snapshot')->nullable()->after('address_id');
            });
        }

        // 2. Backfill: Copia os dados do JSON address para address_snapshot
        if (Schema::hasColumn('orders', 'address')) {
            DB::statement('UPDATE orders SET address_snapshot = address WHERE address_snapshot IS NULL AND address IS NOT NULL');
        }

        // 3. Inspeciona se a Foreign Key já existe no MySQL antes de emitir o DDL
        $fkExists = false;
        if (DB::getDriverName() !== 'sqlite') {
            $dbName = DB::getDatabaseName();
            $fkExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $dbName)
                ->where('TABLE_NAME', 'orders')
                ->where('CONSTRAINT_NAME', 'orders_address_id_foreign')
                ->exists();
        }

        if (!$fkExists && Schema::hasTable('addresses') && Schema::hasColumn('orders', 'address_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('address_id')
                    ->references('id')
                    ->on('addresses')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'address_snapshot')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('address_snapshot');
            });
        }
    }
};
