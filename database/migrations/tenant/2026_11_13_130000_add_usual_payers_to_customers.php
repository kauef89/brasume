<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'usual_payers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->json('usual_payers')->nullable()->after('credit_limit');
            });
        }
    }

    public function down(): void {}
};