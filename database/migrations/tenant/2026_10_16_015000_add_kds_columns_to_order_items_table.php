<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items', 'kitchen_id')) {
                    $table->foreignId('kitchen_id')
                        ->nullable()
                        ->after('product_id')
                        ->constrained('kitchens')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('order_items', 'fired_at')) {
                    $table->timestamp('fired_at')->nullable()->after('status');
                }

                if (!Schema::hasColumn('order_items', 'prep_time_minutes')) {
                    $table->integer('prep_time_minutes')->nullable()->after('fired_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'kitchen_id')) {
                    $table->dropForeign(['kitchen_id']);
                    $table->dropColumn('kitchen_id');
                }
                if (Schema::hasColumn('order_items', 'fired_at')) {
                    $table->dropColumn('fired_at');
                }
                if (Schema::hasColumn('order_items', 'prep_time_minutes')) {
                    $table->dropColumn('prep_time_minutes');
                }
            });
        }
    }
};
