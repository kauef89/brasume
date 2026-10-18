<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            // Guarda um JSON com as roles permitidas (ex: ["waiter", "cashier"])
            $table->json('allowed_roles')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropColumn('allowed_roles');
        });
    }
};
