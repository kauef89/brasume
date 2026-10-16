<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_cost_histories', function (Blueprint $table) {
            $table->index(['product_id', 'id'], 'idx_product_cost_histories_composite');
        });
    }

    public function down(): void
    {
        Schema::table('product_cost_histories', function (Blueprint $table) {
            $table->dropIndex('idx_product_cost_histories_composite');
        });
    }
};
