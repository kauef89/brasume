<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renomeia a tabela de NFE para INVOICE
        Schema::rename('received_nfes', 'received_invoices');
    }

    public function down(): void
    {
        // Caso você precise desfazer (rollback)
        Schema::rename('received_invoices', 'received_nfes');
    }
};