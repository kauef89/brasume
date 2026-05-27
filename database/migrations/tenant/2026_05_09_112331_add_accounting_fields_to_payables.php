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
        // 1. Contas a Pagar Eventuais
        if (!Schema::hasColumn("account_payables", "is_personal")) {
            Schema::table("account_payables", function (Blueprint $table) {
                $table->boolean("is_personal")->default(false)->after("status");
                $table
                    ->string("accounting_category")
                    ->nullable()
                    ->after("is_personal");
                $table
                    ->string("accounting_subcategory")
                    ->nullable()
                    ->after("accounting_category");
            });
        }

        // 2. Contas Fixas
        if (!Schema::hasColumn("fixed_accounts", "is_personal")) {
            Schema::table("fixed_accounts", function (Blueprint $table) {
                $table->boolean("is_personal")->default(false)->after("notes");
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn("account_payables", "is_personal")) {
            Schema::table("account_payables", function (Blueprint $table) {
                $table->dropColumn([
                    "is_personal",
                    "accounting_category",
                    "accounting_subcategory",
                ]);
            });
        }

        if (Schema::hasColumn("fixed_accounts", "is_personal")) {
            Schema::table("fixed_accounts", function (Blueprint $table) {
                $table->dropColumn(["is_personal"]);
            });
        }
    }
};
