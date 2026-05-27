<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de Cupons
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('type')->default('fixed'); // 'fixed' ou 'percent'
            $table->decimal('value', 10, 2);
            $table->decimal('min_subtotal', 10, 2)->default(0);
            
            // Limites de Uso
            $table->integer('global_usage_limit')->default(0); // 0 = ilimitado
            $table->integer('monthly_usage_limit')->default(0);
            $table->integer('user_usage_limit')->default(1);
            
            // Datas e Regras
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('first_order_only')->default(false);
            
            // Vamos usar JSON para guardar os IDs das categorias restritas (muito mais rápido que tabela pivô para este caso)
            $table->json('restricted_categories')->nullable(); 
            
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Tabela de Ofertas
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->decimal('promo_price', 10, 2)->nullable();
            
            // Produto que "gatilha" a oferta (opcional)
            $table->foreignId('trigger_product_id')->nullable()->constrained('products')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('coupons');
    }
};