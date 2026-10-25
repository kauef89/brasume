<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pix_payload')) {
                $table->text('pix_payload')->nullable()->after('txid');
            }
            if (!Schema::hasColumn('orders', 'pix_expires_at')) {
                $table->timestamp('pix_expires_at')->nullable()->after('pix_payload');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'pix_expires_at')) {
                $table->timestamp('pix_expires_at')->nullable()->after('pix_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pix_payload', 'pix_expires_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['pix_expires_at']);
        });
    }
};
