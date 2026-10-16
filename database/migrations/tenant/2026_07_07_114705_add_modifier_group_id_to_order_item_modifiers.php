<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->foreignId('modifier_group_id')
                  ->nullable()
                  ->after('modifier_option_id')
                  ->constrained('modifier_groups')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->dropForeign(['modifier_group_id']);
            $table->dropColumn('modifier_group_id');
        });
    }
};
