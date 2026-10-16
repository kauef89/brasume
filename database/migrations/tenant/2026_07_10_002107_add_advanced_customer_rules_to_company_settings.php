<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('require_birth_date')->default(false)->after('customer_registration_mode');
            $table->boolean('allow_minors')->default(true)->after('require_birth_date');
            $table->boolean('require_whatsapp')->default(true)->after('allow_minors');
            $table->boolean('customer_can_edit_profile')->default(true)->after('require_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'require_birth_date',
                'allow_minors',
                'require_whatsapp',
                'customer_can_edit_profile'
            ]);
        });
    }
};
