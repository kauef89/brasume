<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('out_of_stock_mode', 30)
                  ->default('disable_bottom')
                  ->after('predictive_modifiers_mode'); // Define a posição da coluna
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('out_of_stock_mode');
        });
    }
};
