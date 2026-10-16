<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'production_status')) {
                $table->string('production_status', 50)->default('pending')->after('status');
            }
            if (!Schema::hasColumn('order_items', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable()->after('production_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['production_status', 'cancel_reason']);
        });
    }
};
