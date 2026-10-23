<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('terminal_code', 60)
                ->nullable()
                ->index()
                ->after('device_uuid')
                ->comment('Código do terminal na adquirente (ex: APTE0CD9, APT0NV48)');

            $table->string('assignment_scope', 30)
                ->default('counter')
                ->index()
                ->after('terminal_code')
                ->comment('Afetação operacional preferencial: counter, delivery, waiter, floating');

            $table->string('acquirer_name', 50)
                ->nullable()
                ->default('sicredi')
                ->after('assignment_scope')
                ->comment('Adquirente vinculada: sicredi, stone, cielo, rede, pagbank');

            $table->foreignId('default_user_id')
                ->nullable()
                ->after('acquirer_name')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Operador ou estafeta habitualmente associado a esta maquineta');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['default_user_id']);
            $table->dropColumn(['terminal_code', 'assignment_scope', 'acquirer_name', 'default_user_id']);
        });
    }
};
