<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Flyer O2O (Fidelidade / App)
            $table->boolean('print_loyalty_flyer')->default(false)->after('loyalty_min_redeem_value');
            $table->string('loyalty_flyer_mode')->default('manual')->comment('auto, manual')->after('print_loyalty_flyer');

            // Flyer Growth (Presente / Indicação)
            $table->boolean('print_growth_flyer')->default(false)->after('loyalty_flyer_mode');
            $table->string('growth_flyer_mode')->default('manual')->comment('auto, manual')->after('print_growth_flyer');
            $table->decimal('growth_flyer_value', 10, 2)->default(10.00)->after('growth_flyer_mode');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'print_loyalty_flyer',
                'loyalty_flyer_mode',
                'print_growth_flyer',
                'growth_flyer_mode',
                'growth_flyer_value'
            ]);
        });
    }
};
