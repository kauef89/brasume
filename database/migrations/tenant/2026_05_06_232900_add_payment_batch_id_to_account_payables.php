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
        Schema::table("account_payables", function (Blueprint $table) {
            $table
                ->foreignId("payment_batch_id")
                ->nullable()
                ->constrained("payment_batches")
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("account_payables", function (Blueprint $table) {
            //
        });
    }
};
