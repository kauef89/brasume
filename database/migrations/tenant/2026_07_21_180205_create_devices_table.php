<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ex: "Caixa Frontal", "Totem 01"
            $table->string('type')->index(); // Ex: 'pos', 'totem', 'kds'
            $table->string('device_uuid')->unique(); // A impressão digital do navegador
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_active_at')->nullable(); // Para monitoramento de status
            $table->timestamps();
            $table->softDeletes(); // Para não perdermos o histórico se você excluir um dispositivo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
