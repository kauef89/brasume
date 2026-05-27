<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create("legacy_imports_log", function (Blueprint $table) {
            $table->id();
            $table->string("import_tag")->unique();
            $table->string("type"); // v0_consolidated, v1_detailed, v2_fiscal
            $table->integer("items_count")->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("legacy_imports_log");
    }
};
