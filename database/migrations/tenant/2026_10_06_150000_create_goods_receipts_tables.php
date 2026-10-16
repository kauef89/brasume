<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
                $table->string('receipt_number')->nullable();
                $table->date('received_at');
                $table->enum('status', ['pending_invoice', 'reconciled', 'cancelled'])->default('pending_invoice');
                $table->string('document_path')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 10, 4);
                $table->decimal('unit_cost_estimated', 15, 5)->nullable();
                $table->decimal('reconciled_qty', 10, 4)->default(0);
                $table->enum('status', ['pending', 'partial', 'reconciled'])->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
