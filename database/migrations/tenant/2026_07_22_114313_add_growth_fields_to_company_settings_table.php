<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('whatsapp_handle')->nullable()->after('support_whatsapp');
            $table->string('meta_pixel_id')->nullable()->after('loyalty_points_multiplier');
            $table->text('meta_capi_token')->nullable()->after('meta_pixel_id');
            $table->string('google_pixel_id')->nullable()->after('meta_capi_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_handle',
                'meta_pixel_id',
                'meta_capi_token',
                'google_pixel_id',
            ]);
        });
    }
};
