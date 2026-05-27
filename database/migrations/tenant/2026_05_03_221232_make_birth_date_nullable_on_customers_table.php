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
         Schema::table('customers', function (Blueprint $table) {
             // Torna o campo opcional
             $table->date('birth_date')->nullable()->change();
         });
     }

     public function down(): void
     {
         Schema::table('customers', function (Blueprint $table) {
             // Caso precise reverter, ele volta a ser obrigatório
             $table->date('birth_date')->nullable(false)->change();
         });
     }
};
