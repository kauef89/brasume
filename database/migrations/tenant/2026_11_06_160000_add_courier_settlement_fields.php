<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configurações contratuais do Motoboy
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'courier_payment_model')) {
                $table->string('courier_payment_model', 20)->default('fixed')->after('role');
            }
            if (!Schema::hasColumn('employees', 'daily_fee')) {
                $table->decimal('daily_fee', 10, 2)->default(0.00)->after('courier_payment_model')
                    ->comment('Valor fixo da diária do turno (se houver)');
            }
            if (!Schema::hasColumn('employees', 'default_delivery_fee')) {
                $table->decimal('default_delivery_fee', 10, 2)->default(0.00)->after('daily_fee')
                    ->comment('Taxa base por corrida');
            }
            if (!Schema::hasColumn('employees', 'fee_per_km')) {
                $table->decimal('fee_per_km', 10, 2)->default(0.00)->after('default_delivery_fee')
                    ->comment('Taxa paga por km rodado');
            }
            if (!Schema::hasColumn('employees', 'minimum_guaranteed')) {
                $table->decimal('minimum_guaranteed', 10, 2)->default(0.00)->after('fee_per_km')
                    ->comment('Piso mínimo garantido na noite/turno');
            }
        });

        // 2. Taxa de repasse ao motoboy específica por bairro
        Schema::table('neighborhoods', function (Blueprint $table) {
            if (!Schema::hasColumn('neighborhoods', 'courier_fee')) {
                $table->decimal('courier_fee', 10, 2)->nullable()->after('delivery_fee')
                    ->comment('Repasse ao entregador para este bairro. Se nulo, usa a taxa base do motoboy.');
            }
        });

        // 3. Snapshot financeiro da corrida no Pedido
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'courier_id')) {
                $table->foreignId('courier_id')->nullable()->after('customer_id')
                    ->constrained('employees')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'courier_fee')) {
                $table->decimal('courier_fee', 10, 2)->default(0.00)->after('delivery_fee')
                    ->comment('Valor congelado pago ao entregador nesta corrida');
            }
            if (!Schema::hasColumn('orders', 'delivery_distance_km')) {
                $table->decimal('delivery_distance_km', 8, 2)->nullable()->after('courier_fee');
            }
            if (!Schema::hasColumn('orders', 'courier_assigned_at')) {
                $table->timestamp('courier_assigned_at')->nullable()->after('delivery_distance_km');
            }
            if (!Schema::hasColumn('orders', 'courier_settled_at')) {
                $table->timestamp('courier_settled_at')->nullable()->after('courier_assigned_at')
                    ->comment('Timestamp de quitação da corrida no fechamento do caixa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['courier_id']);
            $table->dropColumn(['courier_id', 'courier_fee', 'delivery_distance_km', 'courier_assigned_at', 'courier_settled_at']);
        });

        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropColumn(['courier_fee']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['courier_payment_model', 'daily_fee', 'default_delivery_fee', 'fee_per_km', 'minimum_guaranteed']);
        });
    }
};
