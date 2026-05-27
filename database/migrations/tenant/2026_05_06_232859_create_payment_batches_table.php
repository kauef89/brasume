<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("payment_batches", function (Blueprint $table) {
            $table->id();
            $table->string("description");
            $table->decimal("total_amount", 10, 2);
            $table->string("status")->default("pending");
            $table->date("payment_date")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("payment_batches");
    }
};
