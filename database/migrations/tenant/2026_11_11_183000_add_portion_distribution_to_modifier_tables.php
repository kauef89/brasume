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
        // 1. Coluna de modo de distribuição no grupo (additive vs proportional_split)
        if (Schema::hasTable('modifier_groups') && !Schema::hasColumn('modifier_groups', 'portion_distribution_mode')) {
            Schema::table('modifier_groups', function (Blueprint $table) {
                $table->string('portion_distribution_mode', 30)
                    ->default('additive')
                    ->after('pricing_strategy');
            });
        }

        // 2. Coluna de comportamento da opção (proportional vs fixed)
        if (Schema::hasTable('modifier_options') && !Schema::hasColumn('modifier_options', 'portion_behavior')) {
            Schema::table('modifier_options', function (Blueprint $table) {
                $table->string('portion_behavior', 20)
                    ->default('proportional')
                    ->after('linked_qty');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('modifier_groups') && Schema::hasColumn('modifier_groups', 'portion_distribution_mode')) {
            Schema::table('modifier_groups', function (Blueprint $table) {
                $table->dropColumn('portion_distribution_mode');
            });
        }

        if (Schema::hasTable('modifier_options') && Schema::hasColumn('modifier_options', 'portion_behavior')) {
            Schema::table('modifier_options', function (Blueprint $table) {
                $table->dropColumn('portion_behavior');
            });
        }
    }
};