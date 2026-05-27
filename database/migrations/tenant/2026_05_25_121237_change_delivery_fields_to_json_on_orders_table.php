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
         Schema::table('orders', function (Blueprint $table) {
             // Mudamos o campo para text/json para suportar o array de intenções
             $table->json('delivery_payment_methods')->nullable()->after('delivery_payment_status');
             // Podemos remover a string antiga caso queira limpar
             $table->dropColumn('delivery_payment_method');
         });
     }
};
