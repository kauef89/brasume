<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_losses')) {
            Schema::create('inventory_losses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('loss_type', 50)->default('operational')->index();
                $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
                $table->decimal('quantity', 10, 4);
                $table->decimal('unit_cost', 15, 5)->default(0);
                $table->decimal('total_cost', 15, 5)->default(0);
                $table->string('reason');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_losses');
    }
};
