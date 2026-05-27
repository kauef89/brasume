<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Só cria a tabela se ela NÃO existir
        if (!Schema::hasTable('account_receivables')) {
            Schema::create('account_receivables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                
                $table->string('description');
                $table->string('payment_method')->nullable();
                $table->string('installment')->default('1/1');
                
                $table->decimal('gross_amount', 10, 2);
                $table->decimal('fee_amount', 10, 2)->default(0);
                $table->decimal('net_amount', 10, 2);
                
                $table->date('due_date');
                $table->date('payment_date')->nullable();
                
                $table->enum('status', ['pending', 'paid', 'anticipated', 'cancelled'])->default('pending');
                
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivables');
    }
};