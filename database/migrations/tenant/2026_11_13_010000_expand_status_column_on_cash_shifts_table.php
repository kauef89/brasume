<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `cash_shifts` MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `cash_shifts` MODIFY COLUMN `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open'");
    }
};
