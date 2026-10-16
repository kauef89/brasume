<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se a tabela não existir neste banco (ex: testes em SQLite), ignora
        if (! Schema::hasTable('shift_attendance')) {
            return;
        }

        Schema::table('shift_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('shift_attendance', 'assigned_role')) {
                $table->string('assigned_role')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('shift_attendance') && Schema::hasColumn('shift_attendance', 'assigned_role')) {
            Schema::table('shift_attendance', function (Blueprint $table) {
                $table->dropColumn('assigned_role');
            });
        }
    }
};
