<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('technical_sheets', function (Blueprint $table) {
            // 🚀 DECISÃO MADURA: Verifica se a coluna já existe antes de criar.
            // Isso salva a vida do tenant "nativa" que já recebeu a coluna via Tinker!
            if (!Schema::hasColumn('technical_sheets', 'order_type')) {
                $table->string('order_type')->default('all')->after('quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technical_sheets', function (Blueprint $table) {
            if (Schema::hasColumn('technical_sheets', 'order_type')) {
                $table->dropColumn('order_type');
            }
        });
    }
};
