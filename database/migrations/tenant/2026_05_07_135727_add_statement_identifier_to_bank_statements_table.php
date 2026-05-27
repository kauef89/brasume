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
                ->string("statement_identifier")
                ->nullable()
                ->after("document")
                ->index()
                ->comment("ID único extraído do memo (ex: ID da Casan)");
        });
    }

    public function down(): void
    {
        Schema::table("bank_statements", function (Blueprint $table) {
            $table->dropColumn("statement_identifier");
        });
    }
};
