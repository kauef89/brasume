<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streets', function (Blueprint $table) {
            if (!Schema::hasColumn('streets', 'neighborhood_id')) {
                $table->unsignedBigInteger('neighborhood_id')->nullable()->after('name');
            }
            if (!Schema::hasColumn('streets', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('neighborhood_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('streets', function (Blueprint $table) {
            $table->dropColumn(['neighborhood_id', 'is_active']);
        });
    }
};
