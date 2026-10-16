<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_cost_histories', function (Blueprint $table) {
            $table->string('xml_name')->nullable()->change();
            $table->string('nfe_key')->nullable()->change();
            $table->nullableMorphs('origin'); // origin_type, origin_id
            $table->string('source_type', 30)->default('nfe'); // 'nfe', 'goods_receipt', 'manual'
        });
    }

    public function down(): void
    {
        Schema::table('product_cost_histories', function (Blueprint $table) {
            $table->dropMorphs('origin');
            $table->dropColumn('source_type');
            $table->string('xml_name')->nullable(false)->change();
            $table->string('nfe_key')->nullable(false)->change();
        });
    }
};
