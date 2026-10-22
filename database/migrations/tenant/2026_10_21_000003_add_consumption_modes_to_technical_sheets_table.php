<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_sheets', function (Blueprint $table) {
            $table->boolean('is_dine_in')->default(true)->after('quantity')->comment('Aplica no Consumo no Local');
            $table->boolean('is_takeaway')->default(true)->after('is_dine_in')->comment('Aplica Para Viagem');
        });

        // 🚀 Migra com segurança os registros legados baseados no order_type anterior
        $sheets = DB::table('technical_sheets')->get();
        foreach ($sheets as $s) {
            $orderType = json_decode($s->order_type, true) ?: [];

            $isDineIn = empty($orderType) || in_array('table', $orderType) || in_array('counter', $orderType);
            $isTakeaway = empty($orderType) || in_array('delivery', $orderType);

            DB::table('technical_sheets')->where('id', $s->id)->update([
                'is_dine_in'  => $isDineIn,
                'is_takeaway' => $isTakeaway,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('technical_sheets', function (Blueprint $table) {
            $table->dropColumn(['is_dine_in', 'is_takeaway']);
        });
    }
};
