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
                $table->boolean('has_food_allowance')->default(false)->after('is_closed_indefinitely');
            }
            if (!Schema::hasColumn('company_settings', 'allow_employee_tab')) {
                $table->boolean('allow_employee_tab')->default(true)->after('has_food_allowance');
            }
            if (!Schema::hasColumn('company_settings', 'self_service_enabled')) {
                $table->boolean('self_service_enabled')->default(false)->after('allow_employee_tab');
            }
            if (!Schema::hasColumn('company_settings', 'allowance_frequency')) {
                $table->string('allowance_frequency', 20)->default('daily')->after('self_service_enabled');
            }
            if (!Schema::hasColumn('company_settings', 'allowance_scope')) {
                $table->string('allowance_scope', 20)->default('global')->after('allowance_frequency');
            }
            if (!Schema::hasColumn('company_settings', 'global_allowance_amount')) {
                $table->decimal('global_allowance_amount', 10, 2)->nullable()->default(0)->after('allowance_scope');
            }
            if (!Schema::hasColumn('company_settings', 'meal_allowance_categories')) {
                $table->json('meal_allowance_categories')->nullable()->after('global_allowance_amount');
            }
            if (!Schema::hasColumn('company_settings', 'meal_allowance_products')) {
                $table->json('meal_allowance_products')->nullable()->after('meal_allowance_categories');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $columns = [
                'has_food_allowance',
                'allow_employee_tab',
                'self_service_enabled',
                'allowance_frequency',
                'allowance_scope',
                'global_allowance_amount',
                'meal_allowance_categories',
                'meal_allowance_products',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('company_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
