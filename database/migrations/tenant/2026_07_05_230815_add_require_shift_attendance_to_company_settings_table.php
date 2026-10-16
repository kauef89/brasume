<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // 🚀 Corrigido de 'cash_carry_over_mode' para 'carry_over_mode'
            $table->boolean('require_shift_attendance')->default(false)->after('carry_over_mode');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('require_shift_attendance');
        });
    }
};
