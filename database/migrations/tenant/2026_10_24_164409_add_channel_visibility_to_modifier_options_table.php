<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('modifier_options')) {
            Schema::table('modifier_options', function (Blueprint $table) {
                if (!Schema::hasColumn('modifier_options', 'show_on_pos')) {
                    $table->boolean('show_on_pos')->default(true)->after('is_active');
                }
                if (!Schema::hasColumn('modifier_options', 'show_on_delivery')) {
                    $table->boolean('show_on_delivery')->default(true)->after('show_on_pos');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('modifier_options')) {
            Schema::table('modifier_options', function (Blueprint $table) {
                if (Schema::hasColumn('modifier_options', 'show_on_delivery')) {
                    $table->dropColumn('show_on_delivery');
                }
                if (Schema::hasColumn('modifier_options', 'show_on_pos')) {
                    $table->dropColumn('show_on_pos');
                }
            });
        }
    }
};