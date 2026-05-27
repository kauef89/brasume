<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            
            // Dados Pessoais
            $table->string('name');
            $table->string('cpf')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            // Dados Bancários (PIX)
            $table->string('pix_key_type')->nullable(); // cpf, phone, email, random
            $table->string('pix_key')->nullable();
            
            // Contrato e Regras
            $table->string('employment_type'); // clt_44, clt_40, freelance_fixed, freelance_eventual
            $table->decimal('base_remuneration', 10, 2); // Salário base ou Diária
            $table->string('payment_cycle')->default('monthly'); // monthly, biweekly, weekly, daily
            
            // Escala Fixa do Freelancer (Armazenado como array/JSON)
            $table->json('work_days')->nullable(); 
            
            // Regras CLT / Adicionais
            $table->boolean('receives_night_premium')->default(false);
            $table->boolean('receives_hazard_pay')->default(false);
            $table->decimal('transportation_voucher', 10, 2)->default(0); // Vale Transporte
            
            $table->timestamps();
            $table->softDeletes(); // Permite "demitir" sem apagar o histórico de vendas/pagamentos
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};