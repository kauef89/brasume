<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table("order_items", function (Blueprint $table) {
            // O item filho pertence a um item pai do mesmo pedido
            $table
                ->foreignId("parent_item_id")
                ->nullable()
                ->constrained("order_items")
                ->cascadeOnDelete();
            $table->boolean("is_combo_item")->default(false); // Flag útil para relatórios e KDS
        });
    }

    public function down()
    {
        Schema::table("order_items", function (Blueprint $table) {
            $table->dropForeign(["parent_item_id"]);
            $table->dropColumn(["parent_item_id", "is_combo_item"]);
        });
    }
};
