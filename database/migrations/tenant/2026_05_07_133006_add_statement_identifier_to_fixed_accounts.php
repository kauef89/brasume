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
        Schema::table("fixed_accounts", function (Blueprint $table) {
            $table
                ->string("statement_identifier")
                ->nullable()
                ->after("supplier_id")
                ->comment(
                    "ID ou texto único que vem no extrato (ex: ID 08759782 da Casan)",
                );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("fixed_accounts", function (Blueprint $table) {
            //
        });
    }
};
