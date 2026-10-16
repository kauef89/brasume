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
        Schema::table('company_settings', function (Blueprint $table) {
            // 📝 1. Bloco de Identidade Visual e Customização
            if (!Schema::hasColumn('company_settings', 'app_name')) {
                $table->string('app_name')->nullable()->after('trade_name');
            }

            // 🖨️ 2. Bloco da Ponte de Impressão Local (Bridge)
            if (!Schema::hasColumn('company_settings', 'print_bridge_token')) {
                $table->string('print_bridge_token')->nullable()->after('kitchen_print_mode');
                $table->string('print_bridge_version')->default('1.0.0')->after('print_bridge_token');
                $table->timestamp('print_bridge_connected_at')->nullable()->after('print_bridge_version');
            }

            // 🧾 3. Bloco Fiscal de Homologação e Numeração de NFC-e
            if (!Schema::hasColumn('company_settings', 'csc_id_homologation')) {
                $table->string('csc_id_homologation')->nullable()->after('csc_code');
                $table->text('csc_code_homologation')->nullable()->after('csc_id_homologation');
                $table->string('nfce_series')->default('1')->after('csc_code_homologation');
                $table->integer('last_nfce_number')->default(0)->after('nfce_series');
            }

            // 💰 4. Bloco de Segurança de Caixa e Turnos do PDV
            if (!Schema::hasColumn('company_settings', 'cash_management_mode')) {
                $table->string('cash_management_mode')->default('owner')->after('cash_opening_mode');
                $table->decimal('cash_difference_tolerance', 10, 2)->default(5.00)->after('cash_management_mode');
            }

            // 🏦 5. Bloco Bancário (Integração Direta Gateway Sicredi Pix)
            if (!Schema::hasColumn('company_settings', 'sicredi_is_active')) {
                $table->boolean('sicredi_is_active')->default(false);
                $table->string('sicredi_chave_pix')->nullable();
                $table->string('sicredi_client_id')->nullable();
                $table->string('sicredi_client_secret')->nullable();
                $table->string('sicredi_cert_path')->nullable();
                $table->string('sicredi_key_path')->nullable();
            }

            // 💳 6. Bloco Gateway Secundário (Mercado Pago)
            if (!Schema::hasColumn('company_settings', 'mp_is_active')) {
                $table->boolean('mp_is_active')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'app_name',
                'print_bridge_token',
                'print_bridge_version',
                'print_bridge_connected_at',
                'csc_id_homologation',
                'csc_code_homologation',
                'nfce_series',
                'last_nfce_number',
                'cash_management_mode',
                'cash_difference_tolerance',
                'sicredi_is_active',
                'sicredi_chave_pix',
                'sicredi_client_id',
                'sicredi_client_secret',
                'sicredi_cert_path',
                'sicredi_key_path',
                'mp_is_active'
            ]);
        });
    }
};
