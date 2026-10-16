<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_storage_location', function (Blueprint $table) {
            // current_stock decimal para bater com o padrão decimal(10,3) da tabela products
            $table->decimal('current_stock', 10, 3)->default(0)->after('max_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('product_storage_location', function (Blueprint $table) {
            $table->dropColumn('current_stock');
        });
    }
};
