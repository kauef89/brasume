<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Contato
            if (!Schema::hasColumn('suppliers', 'email')) {
                $table->string('email')->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'phone')) {
                $table->string('phone', 20)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'pix_key')) {
                $table->string('pix_key')->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'website')) {
                $table->string('website')->nullable();
            }

            // Endereço (Localização)
            if (!Schema::hasColumn('suppliers', 'zip_code')) {
                $table->string('zip_code', 10)->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'street')) {
                $table->string('street')->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'number')) {
                $table->string('number')->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'neighborhood')) {
                $table->string('neighborhood')->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('suppliers', 'state')) {
                $table->string('state', 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $columnsToDrop = [];

            $checkColumns = [
                'email', 'phone', 'pix_key', 'website',
                'zip_code', 'street', 'number', 'neighborhood', 'city', 'state'
            ];

            foreach ($checkColumns as $col) {
                if (Schema::hasColumn('suppliers', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};