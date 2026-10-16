<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->boolean('is_inventory_task')->default(false)->after('description');
            $table->string('inventory_item_type')->nullable()->after('is_inventory_task');
            $table->unsignedBigInteger('inventory_item_id')->nullable()->after('inventory_item_type');

            $table->index(['inventory_item_type', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->dropIndex(['inventory_item_type', 'inventory_item_id']);
            $table->dropColumn(['is_inventory_task', 'inventory_item_type', 'inventory_item_id']);
        });
    }
};
