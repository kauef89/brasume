<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'payment_method')) {
                $table->string('payment_method', 20)->default('pix')->after('base_remuneration');
            }
            if (!Schema::hasColumn('employees', 'bank_agency')) {
                $table->string('bank_agency', 6)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('employees', 'bank_account')) {
                $table->string('bank_account', 15)->nullable()->after('bank_agency');
            }
            if (!Schema::hasColumn('employees', 'bank_account_digit')) {
                $table->string('bank_account_digit', 2)->nullable()->after('bank_account');
            }
        });

        Schema::table('payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payslips', 'bank_agency')) {
                $table->string('bank_agency', 6)->nullable()->after('pix_key');
            }
            if (!Schema::hasColumn('payslips', 'bank_account')) {
                $table->string('bank_account', 15)->nullable()->after('bank_agency');
            }
            if (!Schema::hasColumn('payslips', 'bank_account_digit')) {
                $table->string('bank_account_digit', 2)->nullable()->after('bank_account');
            }
            if (!Schema::hasColumn('payslips', 'payment_batch_id')) {
                $table->foreignId('payment_batch_id')->nullable()->after('bank_statement_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'bank_agency', 'bank_account', 'bank_account_digit']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['bank_agency', 'bank_account', 'bank_account_digit', 'payment_batch_id']);
        });
    }
};
