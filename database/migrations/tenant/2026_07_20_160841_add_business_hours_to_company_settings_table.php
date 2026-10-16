<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // 🚀 1. Só cria se não tiver sido criada na tentativa que falhou
            if (!Schema::hasColumn('company_settings', 'enforce_cashier_open')) {
                $table->boolean('enforce_cashier_open')->default(true)->after('require_shift_attendance');
            }

            // 🚀 2. Aponta para a coluna antiga e estável (require_shift_attendance)
            if (!Schema::hasColumn('company_settings', 'allow_pre_orders')) {
                $table->boolean('allow_pre_orders')->default(false)->after('require_shift_attendance');
            }

            if (!Schema::hasColumn('company_settings', 'pre_order_buffer_minutes')) {
                $table->integer('pre_order_buffer_minutes')->nullable()->after('require_shift_attendance');
            }

            if (!Schema::hasColumn('company_settings', 'business_hours')) {
                $table->json('business_hours')->nullable()->after('require_shift_attendance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('company_settings', 'enforce_cashier_open')) $columns[] = 'enforce_cashier_open';
            if (Schema::hasColumn('company_settings', 'allow_pre_orders')) $columns[] = 'allow_pre_orders';
            if (Schema::hasColumn('company_settings', 'pre_order_buffer_minutes')) $columns[] = 'pre_order_buffer_minutes';
            if (Schema::hasColumn('company_settings', 'business_hours')) $columns[] = 'business_hours';

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
