<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('goods_receipts', 'payment_origin')) {
                $table->string('payment_origin')->default('pending_invoice')->after('receipt_number');
            }
            if (!Schema::hasColumn('goods_receipts', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0)->after('payment_origin');
            }
            if (!Schema::hasColumn('goods_receipts', 'due_date')) {
                $table->date('due_date')->nullable()->after('received_at');
            }
            if (!Schema::hasColumn('goods_receipts', 'nfe_key')) {
                $table->string('nfe_key', 44)->nullable()->after('status');
            }
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            if (!Schema::hasColumn('goods_receipt_items', 'raw_quantity')) {
                $table->decimal('raw_quantity', 12, 4)->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('goods_receipt_items', 'conversion_factor')) {
                $table->decimal('conversion_factor', 10, 4)->default(1)->after('raw_quantity');
            }
            if (!Schema::hasColumn('goods_receipt_items', 'purchase_uom')) {
                $table->string('purchase_uom', 20)->nullable()->after('conversion_factor');
            }
            if (!Schema::hasColumn('goods_receipt_items', 'nfe_key')) {
                $table->string('nfe_key', 44)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn(['payment_origin', 'total_amount', 'due_date', 'nfe_key']);
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropColumn(['raw_quantity', 'conversion_factor', 'purchase_uom', 'nfe_key']);
        });
    }
};
