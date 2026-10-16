<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legacy_code')->nullable();
            $table->foreignId('current_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_ignored')->default(false);
            $table->timestamps();
        });

        Schema::create('legacy_order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_order_item_id')->constrained('legacy_order_items')->cascadeOnDelete();
            $table->foreignId('legacy_modifier_id')->constrained('legacy_modifiers')->cascadeOnDelete();
            $table->decimal('quantity', 10, 4)->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_order_item_modifiers');
        Schema::dropIfExists('legacy_modifiers');
    }
};
