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
        Schema::table("legacy_orders", function (Blueprint $table) {
            $table->longText("xml_content")->nullable()->after("status");
        });
    }

    public function down(): void
    {
        Schema::table("legacy_orders", function (Blueprint $table) {
            $table->dropColumn("xml_content");
        });
    }
};
