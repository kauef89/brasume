<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasIndex('order_items', 'order_items_order_status_prod_index')) {
                $table->index(['order_id', 'status', 'production_status'], 'order_items_order_status_prod_index');
            }
            if (!Schema::hasIndex('order_items', 'order_items_prod_status_index')) {
                $table->index(['production_status', 'status'], 'order_items_prod_status_index');
            }
        });

        Schema::table('bank_statements', function (Blueprint $table) {
            if (!Schema::hasIndex('bank_statements', 'bank_statements_status_deleted_index')) {
                $table->index(['status', 'deleted_at'], 'bank_statements_status_deleted_index');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasIndex('transactions', 'transactions_account_date_index')) {
                $table->index(['bank_account_id', 'transaction_date'], 'transactions_account_date_index');
            }
        });

        Schema::table('fiscal_documents', function (Blueprint $table) {
            if (!Schema::hasIndex('fiscal_documents', 'fiscal_documents_order_status_index')) {
                $table->index(['order_id', 'status'], 'fiscal_documents_order_status_index');
            }
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (!Schema::hasIndex('inventory_movements', 'inventory_movements_origin_type_index')) {
                $table->index(['origin_id', 'type'], 'inventory_movements_origin_type_index');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasIndex('payments', 'payments_status_index')) {
                $table->index(['status'], 'payments_status_index');
            }
            if (!Schema::hasIndex('payments', 'payments_transaction_id_index')) {
                $table->index(['transaction_id'], 'payments_transaction_id_index');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasIndex('products', 'products_menu_status_type_index')) {
                $table->index(['show_on_menu', 'status', 'type'], 'products_menu_status_type_index');
            }
            if (!Schema::hasIndex('products', 'products_category_menu_index')) {
                $table->index(['category_id', 'show_on_menu'], 'products_category_menu_index');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasIndex('customers', 'customers_active_deleted_index')) {
                $table->index(['is_active', 'deleted_at'], 'customers_active_deleted_index');
            }
        });

        Schema::table('cash_shifts', function (Blueprint $table) {
            if (!Schema::hasIndex('cash_shifts', 'cash_shifts_status_deleted_index')) {
                $table->index(['status', 'deleted_at'], 'cash_shifts_status_deleted_index');
            }
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            if (!Schema::hasIndex('print_jobs', 'print_jobs_status_id_index')) {
                $table->index(['status', 'id'], 'print_jobs_status_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_status_prod_index');
            $table->dropIndex('order_items_prod_status_index');
        });
        Schema::table('bank_statements', fn (Blueprint $t) => $t->dropIndex('bank_statements_status_deleted_index'));
        Schema::table('transactions', fn (Blueprint $t) => $t->dropIndex('transactions_account_date_index'));
        Schema::table('fiscal_documents', fn (Blueprint $t) => $t->dropIndex('fiscal_documents_order_status_index'));
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropIndex('inventory_movements_origin_type_index'));
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_index');
            $table->dropIndex('payments_transaction_id_index');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_menu_status_type_index');
            $table->dropIndex('products_category_menu_index');
        });
        Schema::table('customers', fn (Blueprint $t) => $t->dropIndex('customers_active_deleted_index'));
        Schema::table('cash_shifts', fn (Blueprint $t) => $t->dropIndex('cash_shifts_status_deleted_index'));
        Schema::table('print_jobs', fn (Blueprint $t) => $t->dropIndex('print_jobs_status_id_index'));
    }
};
