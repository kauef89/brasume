<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('received_invoices', 'nsu')) {
            Schema::table('received_invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('nsu')->nullable()->after('nfe_key')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('received_invoices', 'nsu')) {
            Schema::table('received_invoices', function (Blueprint $table) {
                $table->dropColumn('nsu');
            });
        }
    }
};
