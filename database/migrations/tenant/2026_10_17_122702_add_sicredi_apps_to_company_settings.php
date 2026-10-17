<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('sicredi_enable_driver_pix')->default(0)->after('allow_employee_tab');
            $table->boolean('sicredi_enable_pos_pix')->default(0)->after('sicredi_enable_driver_pix');
            $table->boolean('sicredi_enable_waiter_pix')->default(0)->after('sicredi_enable_pos_pix');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sicredi_enable_driver_pix',
                'sicredi_enable_pos_pix',
                'sicredi_enable_waiter_pix'
            ]);
        });
    }
};
