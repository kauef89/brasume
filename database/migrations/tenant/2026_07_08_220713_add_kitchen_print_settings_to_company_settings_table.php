<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Removemos o 'after', assim o Laravel adiciona as colunas de forma segura no final da tabela
            $table->string('kitchen_print_mode')->default('station');
            $table->boolean('kitchen_separate_multiples')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['kitchen_print_mode', 'kitchen_separate_multiples']);
        });
    }
};
