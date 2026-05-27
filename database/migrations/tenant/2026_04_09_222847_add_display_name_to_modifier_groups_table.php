<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            // Adiciona a coluna logo após o nome interno
            $table->string('display_name')->nullable()->after('name');
        });

        // MÁGICA: Preenche os registros antigos automaticamente para não quebrar o frontend!
        DB::statement('UPDATE modifier_groups SET display_name = name WHERE display_name IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};