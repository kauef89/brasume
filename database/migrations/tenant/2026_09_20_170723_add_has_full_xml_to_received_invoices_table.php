<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_invoices', function (Blueprint $table) {
            $table->boolean('has_full_xml')->default(false)->after('status');
            $table->index(['status', 'has_full_xml']);
        });

        // 🚀 BACKFILL HISTÓRICO: Marca como true apenas as notas que têm XML completo (nfeProc)
        DB::table('received_invoices')
            ->whereNotNull('xml_content')
            ->where('xml_content', 'not like', '%<resNFe%')
            ->where('xml_content', 'not like', '%resNFe%')
            ->update(['has_full_xml' => true]);
    }

    public function down(): void
    {
        Schema::table('received_invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'has_full_xml']);
            $table->dropColumn('has_full_xml');
        });
    }
};
