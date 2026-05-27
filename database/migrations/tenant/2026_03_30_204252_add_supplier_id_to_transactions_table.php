<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('transactions', function (Blueprint $table) {
        // Adiciona a coluna, diz que ela faz referência à tabela de suppliers e, se apagar o fornecedor, não apaga a transação (deixa null)
        $table->foreignId('supplier_id')->nullable()->after('id')->constrained('suppliers')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('transactions', function (Blueprint $table) {
        $table->dropForeign(['supplier_id']);
        $table->dropColumn('supplier_id');
    });
}
};
