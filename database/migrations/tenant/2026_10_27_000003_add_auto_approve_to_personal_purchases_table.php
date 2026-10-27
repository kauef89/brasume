<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('personal_purchases', 'auto_approve')) {
            Schema::table('personal_purchases', function (Blueprint $table) {
                $table->boolean('auto_approve')->default(false)->after('total_cost')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('personal_purchases', 'auto_approve')) {
            Schema::table('personal_purchases', function (Blueprint $table) {
                $table->dropColumn('auto_approve');
            });
        }
    }
};
