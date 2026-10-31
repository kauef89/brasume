<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'end_to_end_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('end_to_end_id', 50)->nullable()->after('txid')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'end_to_end_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['end_to_end_id']);
                $table->dropColumn('end_to_end_id');
            });
        }
    }
};
