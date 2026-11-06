<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'track_consumption')) {
                $table->boolean('track_consumption')->default(false)->after('track_inventory');
            }
            if (!Schema::hasColumn('products', 'consumption_cycle_days')) {
                $table->unsignedSmallInteger('consumption_cycle_days')->nullable()->after('track_consumption');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {$table->dropColumn(['track_consumption', 'consumption_cycle_days']);
        });
    }
};
