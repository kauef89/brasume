<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('checklist_tasks', 'instructions')) {
                $table->text('instructions')->nullable()->after('description');
            }
        });
    }

    public function down()
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });
    }
};
