<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up()
     {
         Schema::table('orders', function (Blueprint $table) {
             $table->unsignedBigInteger('driver_id')->nullable()->after('employee_id');

             // Se a sua tabela de motoristas for a 'users' ou 'employees', você pode atrelar a chave estrangeira:
             // $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
         });
     }

     public function down()
     {
         Schema::table('orders', function (Blueprint $table) {
             $table->dropColumn('driver_id');
         });
     }
};
