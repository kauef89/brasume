<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('show_on_delivery', 'show_on_customer_app');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->renameColumn('show_on_delivery', 'show_on_customer_app');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('show_on_customer_app', 'show_on_delivery');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->renameColumn('show_on_customer_app', 'show_on_delivery');
        });
    }
};