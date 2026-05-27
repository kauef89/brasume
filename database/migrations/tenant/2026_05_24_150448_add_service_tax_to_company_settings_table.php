<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table("company_settings", function (Blueprint $table) {
            $table
                ->boolean("has_service_tax")
                ->default(false)
                ->after("environment");
            $table
                ->decimal("service_tax_percentage", 5, 2)
                ->default(10.0)
                ->after("has_service_tax");
        });
    }

    public function down(): void
    {
        Schema::table("company_settings", function (Blueprint $table) {
            $table->dropColumn(["has_service_tax", "service_tax_percentage"]);
        });
    }
};
