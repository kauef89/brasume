<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite não aceita a cláusula MODIFY; essa regra é específica de MySQL/MariaDB
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE transactions MODIFY transaction_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE transactions MODIFY transaction_date TIMESTAMP NOT NULL;');
        }
    }
};
