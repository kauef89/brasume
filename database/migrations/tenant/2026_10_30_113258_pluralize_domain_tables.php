<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_storage_location')) Schema::rename('product_storage_location', 'product_storage_locations');
        if (Schema::hasTable('legacy_imports_log')) Schema::rename('legacy_imports_log', 'legacy_import_logs');
        if (Schema::hasTable('shift_attendance')) Schema::rename('shift_attendance', 'shift_attendances');
    }

    public function down(): void
    {
        if (Schema::hasTable('product_storage_locations')) Schema::rename('product_storage_locations', 'product_storage_location');
        if (Schema::hasTable('legacy_import_logs')) Schema::rename('legacy_import_logs', 'legacy_imports_log');
        if (Schema::hasTable('shift_attendances')) Schema::rename('shift_attendances', 'shift_attendance');
    }
};
