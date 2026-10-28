<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Folhas Mensais (Competências)
        if (!Schema::hasTable('payrolls')) {
            Schema::create('payrolls', function (Blueprint $table) {
                $table->id();
                $table->string('reference_period', 7)->index(); // Ex: '2026-04'
                $table->date('payment_date')->nullable();
                $table->decimal('total_earnings', 12, 2)->default(0.00);
                $table->decimal('total_deductions', 12, 2)->default(0.00);
                $table->decimal('total_net', 12, 2)->default(0.00);
                $table->decimal('total_inss', 12, 2)->default(0.00);
                $table->decimal('total_fgts', 12, 2)->default(0.00);
                $table->decimal('total_irrf', 12, 2)->default(0.00);
                $table->string('status')->default('closed'); // draft, approved, closed, paid
                $table->foreignId('payment_batch_id')->nullable()->constrained('payment_batches')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 2. Holerites Individuais
        if (!Schema::hasTable('payslips')) {
            Schema::create('payslips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('base_salary', 10, 2)->default(0.00);
                $table->decimal('gross_salary', 10, 2)->default(0.00);
                $table->decimal('total_deductions', 10, 2)->default(0.00);
                $table->decimal('net_salary', 10, 2)->default(0.00);
                $table->decimal('inss_amount', 10, 2)->default(0.00);
                $table->decimal('fgts_amount', 10, 2)->default(0.00);
                $table->decimal('irrf_amount', 10, 2)->default(0.00);
                $table->string('payment_method')->default('bank_transfer');
                $table->string('pix_key')->nullable();
                $table->string('status')->default('paid'); // draft, confirmed, paid
                $table->foreignId('bank_statement_id')->nullable()->constrained('bank_statements')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 3. Rubricas do Holerite (Proventos e Descontos)
        if (!Schema::hasTable('payslip_items')) {
            Schema::create('payslip_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
                $table->string('code', 10)->nullable();
                $table->string('name');
                $table->enum('type', ['earning', 'deduction']);
                $table->decimal('reference', 8, 2)->nullable();
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_items');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payrolls');
    }
};
