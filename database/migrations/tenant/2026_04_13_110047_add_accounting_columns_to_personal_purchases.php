<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_purchases', function (Blueprint $table) {
            $table->string('accounting_category')->nullable();
            $table->string('accounting_subcategory')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personal_purchases', function (Blueprint $table) {
            $table->dropColumn(['accounting_category', 'accounting_subcategory']);
        });
    }
};