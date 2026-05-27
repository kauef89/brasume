<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_accounts', function (Blueprint $table) {
            $table->id();
            
            // Dados Básicos e Vínculo
            $table->string('name'); // Ex: Conta de Luz Matriz
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete(); // Ex: Celesc
            $table->string('contract_number')->nullable(); // Número da Unidade Consumidora
            
            // Classificação Contábil
            $table->string('accounting_category')->nullable();
            $table->string('accounting_subcategory')->nullable();
            
            // Regras Financeiras
            $table->integer('due_day'); // Dia do vencimento (1 a 31)
            $table->enum('value_type', ['fixed', 'variable'])->default('variable');
            $table->decimal('default_amount', 10, 2)->nullable(); // Se for fixo, qual o valor?
            
            // Pagamentos e Titularidade
            $table->boolean('is_auto_debit')->default(false); // Débito Automático?
            $table->enum('account_holder', ['company', 'partner', 'third_party'])->default('company'); // Em nome de quem?
            $table->enum('paid_by', ['company', 'partner', 'third_party'])->default('company'); // Quem paga?
            
            // Controle
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_accounts');
    }
};