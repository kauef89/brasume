<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            // order_id é nullable porque notas muito antigas podem não ter o pedido importado no sistema novo
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            
            $table->string('model', 2); // 55 (NFe) ou 65 (NFCe)
            $table->string('series', 3);
            $table->string('number', 20);
            $table->string('access_key', 44)->unique();
            $table->string('protocol', 20)->nullable();
            
            $table->decimal('total_value', 10, 2);
            $table->dateTime('issue_date');
            
            $table->enum('status', ['authorized', 'canceled', 'rejected', 'processing'])->default('authorized');
            
            // Aqui guardaremos o XML da nota no futuro (ou link do PDF)
            $table->longText('xml_content')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};