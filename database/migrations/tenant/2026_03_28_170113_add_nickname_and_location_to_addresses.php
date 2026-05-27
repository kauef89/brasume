<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'nickname')) {
                $table->string('nickname')->default('Casa')->after('id');
            }
            if (!Schema::hasColumn('addresses', 'location_link')) {
                $table->text('location_link')->nullable()->after('reference');
            }
            if (!Schema::hasColumn('addresses', 'no_number')) {
                $table->boolean('no_number')->default(false)->after('number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['nickname', 'location_link', 'no_number']);
        });
    }
};