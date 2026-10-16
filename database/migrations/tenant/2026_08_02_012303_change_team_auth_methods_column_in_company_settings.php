<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Limpamos os dados antigos para evitar que o MySQL reclame ao converter de JSON para String
        DB::table('company_settings')->update(['team_auth_methods' => null]);

        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('team_auth_methods')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('team_auth_methods')->nullable()->change();
        });
    }
};
