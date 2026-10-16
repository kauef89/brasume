<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'name')) {
                $table->string('name')->nullable()->after('code');
            }
            if (!Schema::hasColumn('coupons', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('coupons', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
