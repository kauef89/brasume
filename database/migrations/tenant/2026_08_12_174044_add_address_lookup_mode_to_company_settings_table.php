<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // O padrão será 'cep' para manter a retrocompatibilidade
            $table->string('address_lookup_mode')->default('cep')->after('is_fiscal_emitter');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('address_lookup_mode');
        });
    }
};
