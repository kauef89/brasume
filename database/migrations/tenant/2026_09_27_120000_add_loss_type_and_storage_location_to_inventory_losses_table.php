<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se a tabela não existir neste banco (ex: testes limpos em SQLite), ignora
        if (! Schema::hasTable('inventory_losses')) {
            return;
        }

        Schema::table('inventory_losses', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_losses', 'storage_location_id')) {
                $table->foreignId('storage_location_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('storage_locations')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('inventory_losses', 'loss_type')) {
                $table->string('loss_type', 50)
                    ->default('operational')
                    ->after('user_id')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_losses')) {
            return;
        }

        Schema::table('inventory_losses', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_losses', 'storage_location_id')) {
                $table->dropForeign(['storage_location_id']);
                $table->dropColumn('storage_location_id');
            }

            if (Schema::hasColumn('inventory_losses', 'loss_type')) {
                $table->dropColumn('loss_type');
            }
        });
    }
};
