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
    Schema::create('modifier_groups', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('type'); // observation, add_on, ingredient
        $table->integer('min_selections')->default(0);
        $table->integer('max_selections')->nullable();
        $table->integer('max_free_options')->default(0);
        $table->boolean('allow_multiple_qty')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_groups');
    }
};
