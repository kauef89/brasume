<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('company_settings', 'attention_categories')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->text('attention_categories')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_settings', 'attention_categories')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('attention_categories');
            });
        }
    }
};
