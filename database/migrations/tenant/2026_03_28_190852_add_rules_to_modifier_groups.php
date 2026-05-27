<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('modifier_groups', 'allow_quantity')) {
                $table->boolean('allow_quantity')->default(false)->after('max_selections');
            }
            if (!Schema::hasColumn('modifier_groups', 'max_free')) {
                $table->integer('max_free')->default(0)->after('allow_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn(['allow_quantity', 'max_free']);
        });
    }
};