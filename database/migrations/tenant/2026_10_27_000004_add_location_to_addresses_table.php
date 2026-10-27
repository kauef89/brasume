<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('addresses', 'location')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('location', 500)->nullable()->after('reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('addresses', 'location')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }
    }
};