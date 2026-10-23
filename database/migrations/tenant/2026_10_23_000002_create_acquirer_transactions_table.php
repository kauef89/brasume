<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquirer_transactions', function (Blueprint $table) {
            $table->id();

            // Identificação na adquirente / máquina
            $table->string('acquirer_name', 50)->default('sicredi');
            $table->string('terminal_code', 60)->index();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();

            // Dados da transação capturada
            $table->dateTime('transaction_date')->index();
            $table->string('modality', 30); // debito, credito, via qrcode
            $table->string('brand', 30);    // mastercard, visa, pix
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('fee_amount', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2);

            // Códigos de rastreio e liquidação
            $table->string('authorization_code', 60)->nullable();
            $table->string('nsu', 60)->index();
            $table->date('expected_settlement_date')->nullable();

            // Status e Vínculos com o ERP
            $table->string('status', 30)->default('orphan')->index(); // matched, ambiguous, orphan, ignored
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('account_receivable_id')->nullable()->constrained('account_receivables')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            // 🛡️ Previne reimportar a mesma transação no mesmo terminal
            $table->unique(['acquirer_name', 'terminal_code', 'nsu', 'transaction_date'], 'acquirer_tx_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquirer_transactions');
    }
};
