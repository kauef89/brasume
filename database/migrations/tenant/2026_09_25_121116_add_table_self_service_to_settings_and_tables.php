<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('company_settings', 'allow_table_self_service')) {
                $table->boolean('allow_table_self_service')->default(false)->after('self_service_enabled');
            }
        });

        Schema::table('dining_tables', function (Blueprint $table) {
            if (!Schema::hasColumn('dining_tables', 'hash')) {
                $table->string('hash', 16)->nullable()->unique()->after('id');
            }
        });

        DB::table('dining_tables')->whereNull('hash')->orderBy('id')->chunk(100, function ($tables) {
            foreach ($tables as $table) {
                DB::table('dining_tables')
                    ->where('id', $table->id)
                    ->update(['hash' => substr(md5(uniqid(rand(), true)), 0, 8)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'allow_table_self_service')) {
                $table->dropColumn('allow_table_self_service');
            }
        });

        Schema::table('dining_tables', function (Blueprint $table) {
            if (Schema::hasColumn('dining_tables', 'hash')) {
                $table->dropColumn('hash');
            }
        });
    }
};
