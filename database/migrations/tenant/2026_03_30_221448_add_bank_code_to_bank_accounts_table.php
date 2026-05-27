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
    Schema::table('bank_accounts', function (Blueprint $table) {
        // Adicionando a coluna para guardar o código do banco
        $table->string('bank_code')->nullable()->after('name'); 
    });
}

public function down(): void
{
    Schema::table('bank_accounts', function (Blueprint $table) {
        $table->dropColumn('bank_code');
    });
}
};
