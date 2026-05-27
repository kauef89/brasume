<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('ie')->nullable()->after('cnpj');
            $table->string('csc_id')->nullable();
            $table->string('csc_code')->nullable();
            $table->enum('environment', ['1', '2'])->default('2'); // 2 = Homologação
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['ie', 'csc_id', 'csc_code', 'environment']);
        });
    }
};