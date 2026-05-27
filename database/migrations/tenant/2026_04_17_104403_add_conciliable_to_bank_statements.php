<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            // Cria duas colunas: conciliable_type (ex: App\Models\Payable) e conciliable_id (ex: 15)
            $table->nullableMorphs('conciliable'); 
        });
    }

    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->dropMorphs('conciliable');
        });
    }
};