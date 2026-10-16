<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('google_api_secret')->nullable()->after('google_pixel_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('ga_client_id')->nullable()->after('gclid');
            $table->string('fbp')->nullable()->after('ga_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('google_api_secret');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ga_client_id', 'fbp']);
        });
    }
};
