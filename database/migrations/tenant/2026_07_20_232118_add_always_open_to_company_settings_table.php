<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('is_24_hours')->default(false)->after('business_hours');
            $table->boolean('is_closed_indefinitely')->default(false)->after('is_24_hours');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('company_settings', 'is_24_hours')) $columns[] = 'is_24_hours';
            if (Schema::hasColumn('company_settings', 'is_closed_indefinitely')) $columns[] = 'is_closed_indefinitely';

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
