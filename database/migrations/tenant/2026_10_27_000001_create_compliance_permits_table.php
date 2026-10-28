<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_permits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('other');
            $table->string('issuer_name')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('document_number')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at');
            $table->unsignedInteger('alert_days_before')->default(30);
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->foreignId('account_payable_id')->nullable()->constrained('account_payables')->nullOnDelete();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['expires_at', 'is_active']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_permits');
    }
};