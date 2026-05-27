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
        Schema::table("bank_statements", function (Blueprint $table) {
            $table
                ->string("counterparty_name")
                ->nullable()
                ->after("movement_type")
                ->comment("Nome limpo de quem pagou/recebeu");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("bank_statements", function (Blueprint $table) {
            //
        });
    }
};
