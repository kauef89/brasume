<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('is_fiscal_emitter')->default(false)->after('id');
            $table->string('customer_registration_mode')->default('basic')->after('is_fiscal_emitter');
            $table->string('serpro_client_id')->nullable()->after('customer_registration_mode');
            $table->string('serpro_client_secret')->nullable()->after('serpro_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'is_fiscal_emitter',
                'customer_registration_mode',
                'serpro_client_id',
                'serpro_client_secret'
            ]);
        });
    }
};
