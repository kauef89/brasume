<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('service_tax_table')->default(true)->after('service_tax_percentage');
            $table->boolean('service_tax_counter')->default(false)->after('service_tax_table');
            $table->boolean('service_tax_delivery')->default(false)->after('service_tax_counter');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['service_tax_table', 'service_tax_counter', 'service_tax_delivery']);
        });
    }
};
