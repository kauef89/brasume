<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            // Criamos a taxa de serviço logo após a taxa de entrega para manter a organização
            $table
                ->decimal("service_fee", 10, 2)
                ->default(0)
                ->after("delivery_fee");
        });
    }

    public function down(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->dropColumn("service_fee");
        });
    }
};
