<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_modifiers', function (Blueprint $table) {
            $table->foreignId('current_product_id')->nullable()->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legacy_modifiers', function (Blueprint $table) {
            $table->dropForeign(['current_product_id']);
            $table->dropColumn('current_product_id');
        });
    }
};
