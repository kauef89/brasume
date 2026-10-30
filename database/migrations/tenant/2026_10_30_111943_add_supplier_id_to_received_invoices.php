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
            $table->unsignedBigInteger('supplier_id')->nullable()->after('id');
        });

        // Match automático com base no CNPJ do XML
        DB::statement("
            UPDATE received_invoices ri
            INNER JOIN suppliers s ON ri.emitter_cnpj = s.document
            SET ri.supplier_id = s.id
        ");

        Schema::table('received_invoices', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('received_invoices', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
