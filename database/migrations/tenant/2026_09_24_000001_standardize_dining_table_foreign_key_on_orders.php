<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Assegura a existência da coluna dining_table_id
        if (!Schema::hasColumn('orders', 'dining_table_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('dining_table_id')->nullable()->after('id');
            });
        }

        // 2. Se existia a coluna legada table_id com dados, migra para dining_table_id
        if (Schema::hasColumn('orders', 'table_id')) {
            DB::statement('UPDATE orders SET dining_table_id = table_id WHERE dining_table_id IS NULL AND table_id IS NOT NULL');
        }

        // 3. Se havia pedidos órfãos com apenas table_number textual, faz o backfill relacionando pelo nome da mesa
        if (Schema::hasColumn('orders', 'table_number') && Schema::hasTable('dining_tables')) {
            // O SQLite não suporta UPDATE com INNER JOIN nativamente.
            // Como este backfill é para dados de produção (MySQL), ignoramos em testes (SQLite).
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement('
                    UPDATE orders o
                    INNER JOIN dining_tables dt ON dt.name = o.table_number
                    SET o.dining_table_id = dt.id
                    WHERE o.dining_table_id IS NULL
                ');
            }
        }

        // 4. Cria índice e chave estrangeira caso ainda não existam
        Schema::table('orders', function (Blueprint $table) {
            $table->index('dining_table_id');

            // Verifica se a tabela dining_tables existe antes de atrelar a constraint
            if (Schema::hasTable('dining_tables')) {
                $table->foreign('dining_table_id')
                    ->references('id')
                    ->on('dining_tables')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['dining_table_id']);
            $table->dropIndex(['dining_table_id']);
        });
    }
};
