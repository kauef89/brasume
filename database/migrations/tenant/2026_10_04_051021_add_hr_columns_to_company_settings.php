<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('company_settings', 'has_food_allowance')) {
                $table->boolean('has_food_allowance')->default(false);
            }
            if (!Schema::hasColumn('company_settings', 'allow_employee_tab')) {
                $table->boolean('allow_employee_tab')->default(true);
            }
            if (!Schema::hasColumn('company_settings', 'self_service_enabled')) {
                $table->boolean('self_service_enabled')->default(false);
            }
            if (!Schema::hasColumn('company_settings', 'allowance_frequency')) {
                $table->string('allowance_frequency')->default('daily');
            }
            if (!Schema::hasColumn('company_settings', 'allowance_scope')) {
                $table->string('allowance_scope')->default('global');
            }
            if (!Schema::hasColumn('company_settings', 'global_allowance_amount')) {
                $table->decimal('global_allowance_amount', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'has_food_allowance',
                'allow_employee_tab',
                'self_service_enabled',
                'allowance_frequency',
                'allowance_scope',
                'global_allowance_amount'
            ]);
        });
    }
};