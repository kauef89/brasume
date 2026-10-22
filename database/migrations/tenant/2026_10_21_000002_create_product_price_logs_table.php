<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('channel', 20)->comment('dine_in, delivery');

            // Condições Comerciais Congeladas
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 12, 4)->comment('CMV + Insumos preditivos no momento');
            $table->decimal('total_variables', 10, 2)->default(0)->comment('Impostos + Cartão');
            $table->decimal('contribution_margin', 10, 2);
            $table->decimal('margin_percentage', 6, 2);

            // Vigência da Condição
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable()->comment('Null = Condição Vigente');
            $table->string('trigger_reason', 50)->default('price_change')->comment('price_change, recipe_update, initial_baseline');

            $table->timestamps();

            // Índices para consultas de vendas e relatórios de BI
            $table->index(['product_id', 'channel', 'started_at', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_logs');
    }
};
