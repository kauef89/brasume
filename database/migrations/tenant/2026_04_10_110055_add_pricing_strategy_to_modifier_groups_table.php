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
        Schema::table('modifier_groups', function (Blueprint $table) {
            // Adiciona a estratégia de precificação
            $table->string('pricing_strategy')->default('base_plus_increment')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            //
        });
    }
};
