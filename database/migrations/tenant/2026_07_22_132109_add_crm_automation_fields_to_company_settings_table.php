<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Automação de Aniversário
            $table->boolean('auto_birthday_coupon')->default(false)->after('loyalty_is_active')->comment('Liga/Desliga campanha de aniversário');
            $table->string('birthday_banner_path')->nullable()->after('auto_birthday_coupon');

            // Automação de Churn (Recuperação)
            $table->boolean('auto_churn_coupon')->default(false)->after('birthday_banner_path')->comment('Liga/Desliga campanha de churn');
            $table->integer('churn_days_threshold')->default(30)->after('auto_churn_coupon')->comment('Quantos dias para considerar o cliente sumido');
            $table->string('churn_banner_path')->nullable()->after('churn_days_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_birthday_coupon',
                'birthday_banner_path',
                'auto_churn_coupon',
                'churn_days_threshold',
                'churn_banner_path'
            ]);
        });
    }
};
