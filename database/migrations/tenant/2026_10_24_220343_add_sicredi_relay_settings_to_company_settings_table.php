<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('sicredi_share_pix_key')->default(true)->after('sicredi_enable_waiter_pix');
            $table->string('sicredi_relay_url', 500)->nullable()->after('sicredi_share_pix_key');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['sicredi_share_pix_key', 'sicredi_relay_url']);
        });
    }
};
