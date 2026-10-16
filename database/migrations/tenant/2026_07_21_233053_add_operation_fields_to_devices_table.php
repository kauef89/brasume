<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (!Schema::hasColumn('devices', 'uuid')) {
                $table->uuid('uuid')->unique()->after('id');
            }
            if (!Schema::hasColumn('devices', 'name')) {
                $table->string('name')->nullable()->after('uuid');
            }
            if (!Schema::hasColumn('devices', 'type')) {
                $table->string('type')->default('pos')->after('name');
            }
            if (!Schema::hasColumn('devices', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('type');
            }
            if (!Schema::hasColumn('devices', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'name', 'type', 'is_active', 'last_active_at']);
        });
    }
};
