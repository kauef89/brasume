<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('supplier_id')->constrained('employees')->nullOnDelete();
            }
            if (!Schema::hasColumn('transactions', 'labor_type')) {
                $table->string('labor_type', 30)->nullable()->after('category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'employee_id')) {
                $table->dropConstrainedForeignId('employee_id');
            }
            if (Schema::hasColumn('transactions', 'labor_type')) {
                $table->dropColumn('labor_type');
            }
        });
    }
};