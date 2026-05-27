<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('account_payables', function (Blueprint $table) {
        // As colunas de rastreio financeiro da Conta Fixa
        $table->foreignId('fixed_account_id')->nullable()->constrained('fixed_accounts')->nullOnDelete();
        $table->boolean('is_auto_debit')->default(false);
        $table->string('account_holder')->nullable();
        $table->string('paid_by')->nullable();
        $table->string('contract_number')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            //
        });
    }
};
