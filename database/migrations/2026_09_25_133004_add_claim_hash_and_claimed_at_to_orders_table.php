<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('claim_hash', 32)->nullable()->unique()->after('customer_id');
            $table->timestamp('claimed_at')->nullable()->after('claim_hash');
            $table->timestamp('claim_expires_at')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['claim_hash', 'claimed_at', 'claim_expires_at']);
        });
    }
};
