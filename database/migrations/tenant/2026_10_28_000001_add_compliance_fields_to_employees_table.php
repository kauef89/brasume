<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'accounting_code')) {
                $table->string('accounting_code', 20)->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('employees', 'job_title')) {
                $table->string('job_title', 100)->nullable()->after('name');
            }
            if (!Schema::hasColumn('employees', 'cbo_code')) {
                $table->string('cbo_code', 20)->nullable()->after('job_title');
            }
            if (!Schema::hasColumn('employees', 'pis_pasep')) {
                $table->string('pis_pasep', 30)->nullable()->after('cpf');
            }
            if (!Schema::hasColumn('employees', 'ctps_number')) {
                $table->string('ctps_number', 50)->nullable()->after('pis_pasep');
            }
            if (!Schema::hasColumn('employees', 'admission_date')) {
                $table->date('admission_date')->nullable()->after('ctps_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $columns = ['accounting_code', 'job_title', 'cbo_code', 'pis_pasep', 'ctps_number', 'admission_date'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
