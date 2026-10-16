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
         Schema::table('company_settings', function (Blueprint $table) {
             $table->boolean('guest_restricted_payments')->default(true)->after('customer_allow_guest');
         });
     }

     public function down(): void
     {
         Schema::table('company_settings', function (Blueprint $table) {
             $table->dropColumn('guest_restricted_payments');
         });
     }
};
