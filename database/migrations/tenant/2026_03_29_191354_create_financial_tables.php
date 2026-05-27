<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Contas Bancárias / Cofre
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('checking'); // vault (cofre), checking (corrente), savings (poupança)
            $table->decimal('initial_balance', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Tabela de Cartões de Crédito Corporativos
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('limit', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // 3. Tabela de Formas de Pagamento (Entradas)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('engine_type')->nullable(); // money, pix, manual, etc
            $table->string('fiscal_code')->nullable();
            $table->boolean('show_on_pos')->default(true);
            $table->boolean('show_on_delivery')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('icon')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('credit_cards');
        Schema::dropIfExists('bank_accounts');
    }
};