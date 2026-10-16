<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('home_bg_open')->nullable()->after('theme_color');
            $table->json('home_bg_closed')->nullable()->after('home_bg_open');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('company_settings', 'home_bg_open')) $columns[] = 'home_bg_open';
            if (Schema::hasColumn('company_settings', 'home_bg_closed')) $columns[] = 'home_bg_closed';

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
