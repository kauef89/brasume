<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'device_uuid')) {
            return;
        }

        // 1. Remove o índice UNIQUE previamente (exigência estrita do SQLite)
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS devices_device_uuid_unique');
            } else {
                Schema::table('devices', function (Blueprint $table) {
                    $table->dropUnique(['device_uuid']);
                });
            }
        } catch (\Throwable $e) {
            // Ignora caso o índice já tenha sido descartado
        }

        // 2. Remove a coluna com segurança
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('device_uuid');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('devices', 'device_uuid')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->uuid('device_uuid')->nullable()->unique();
            });
        }
    }
};
