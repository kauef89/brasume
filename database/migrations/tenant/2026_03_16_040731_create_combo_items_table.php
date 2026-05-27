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
    Schema::create('combo_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('combo_group_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
        $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
        $table->decimal('additional_price', 10, 2)->default(0); // Ex: + R$ 3,00 pela batata grande
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combo_items');
    }
};
