<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Adicionamos as colunas logo após o company_name para manter a organização
            $table->string('logo_path')->nullable()->after('company_name');
            $table->string('icon_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'icon_path']);
        });
    }
};
