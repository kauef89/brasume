<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_expenses', function (Blueprint $table) {
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'bimonthly', 'quarterly', 'semiannual', 'yearly'])->default('monthly')->after('accounting_subcategory');
            $table->tinyInteger('due_weekday')->nullable()->after('frequency')->comment('0=Sunday, 6=Saturday');
            $table->tinyInteger('due_month')->nullable()->after('due_day')->comment('1=Jan, 12=Dec');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete()->after('supplier_id');
            $table->timestamp('last_generated_at')->nullable()->after('is_personal');
            
            $table->integer('due_day')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_expenses', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn(['frequency', 'due_weekday', 'due_month', 'employee_id', 'last_generated_at']);
            $table->integer('due_day')->nullable(false)->change();
        });
    }
};