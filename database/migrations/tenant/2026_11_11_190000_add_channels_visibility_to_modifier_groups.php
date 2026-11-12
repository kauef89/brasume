<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modifier_groups')) {
            Schema::table('modifier_groups', function (Blueprint $table) {
                if (!Schema::hasColumn('modifier_groups', 'show_on_pos')) {
                    $table->boolean('show_on_pos')->default(true)->after('is_active');
                }
                if (!Schema::hasColumn('modifier_groups', 'show_on_customer_app')) {
                    $table->boolean('show_on_customer_app')->default(true)->after('show_on_pos');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modifier_groups')) {
            Schema::table('modifier_groups', function (Blueprint $table) {
                if (Schema::hasColumn('modifier_groups', 'show_on_customer_app')) {
                    $table->dropColumn('show_on_customer_app');
                }
                if (Schema::hasColumn('modifier_groups', 'show_on_pos')) {
                    $table->dropColumn('show_on_pos');
                }
            });
        }
    }
};