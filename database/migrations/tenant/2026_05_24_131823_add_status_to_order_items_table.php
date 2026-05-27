<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table("order_items", function (Blueprint $table) {
            $table->string("status")->default("active"); // active, cancelled
            $table->string("cancel_reason")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("order_items", function (Blueprint $table) {
            //
        });
    }
};
