<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->string('nfe_key', 44)->nullable()->after('id'); // Guarda a chave da nota
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')->constrained('suppliers')->nullOnDelete(); // Vincula ao Fornecedor
        });
    }

    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['nfe_key', 'supplier_id']);
        });
    }
};