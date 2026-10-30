<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_modifier')) {
            Schema::rename('product_modifier', 'product_modifiers');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_modifiers')) {
            Schema::rename('product_modifiers', 'product_modifier');
        }
    }
};
