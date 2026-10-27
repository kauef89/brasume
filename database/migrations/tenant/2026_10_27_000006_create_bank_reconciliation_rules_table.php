<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank_reconciliation_rules')) {
            Schema::create('bank_reconciliation_rules', function (Blueprint $table) {
                $table->id();
                $table->string('search_term')->index();
                $table->string('match_type')->default('contains'); // contains, exact
                $table->string('target_type')->default('expense'); // expense, personal, transfer
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->string('accounting_category')->nullable();
                $table->string('accounting_subcategory')->nullable();
                $table->boolean('auto_reconcile')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_rules');
    }
};
