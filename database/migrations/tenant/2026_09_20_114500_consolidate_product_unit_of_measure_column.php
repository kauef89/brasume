<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Garante que qualquer registro sem unit_of_measure receba o valor de unit_measure em maiúsculo
        if (Schema::hasColumn('products', 'unit_measure') && Schema::hasColumn('products', 'unit_of_measure')) {
            DB::table('products')
                ->whereNull('unit_of_measure')
                ->orWhere('unit_of_measure', '')
                ->update([
                    'unit_of_measure' => DB::raw("UPPER(COALESCE(unit_measure, 'UN'))")
                ]);

            // 2. Remove a coluna redundante unit_measure
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('unit_measure');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'unit_measure')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('unit_measure', 10)->default('un')->after('type');
            });

            DB::table('products')->update([
                'unit_measure' => DB::raw("LOWER(COALESCE(unit_of_measure, 'un'))")
            ]);
        }
    }
};
