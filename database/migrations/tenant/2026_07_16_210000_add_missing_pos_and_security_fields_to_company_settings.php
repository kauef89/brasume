<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {

            // ==========================================
            // CAMPOS DE IMPRESSÃO (PosSchema)
            // ==========================================
            if (!Schema::hasColumn('company_settings', 'checkout_print_mode')) {
                // 🚀 after() removido daqui para garantir a criação segura
                $table->string('checkout_print_mode')->default('ask')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'kitchen_print_mode')) {
                $table->string('kitchen_print_mode')->default('station')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'kds_access_mode')) {
                $table->string('kds_access_mode')->default('role')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'kitchen_separate_multiples')) {
                $table->boolean('kitchen_separate_multiples')->default(false);
            }

            // ==========================================
            // CAMPOS DE SEGURANÇA E LOGIN (SecuritySchema)
            // ==========================================
            if (!Schema::hasColumn('company_settings', 'pos_security_mode')) {
                $table->string('pos_security_mode')->default('standard')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'pos_secure_actions')) {
                $table->json('pos_secure_actions')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'customer_allow_guest')) {
                $table->boolean('customer_allow_guest')->default(true);
            }
            if (!Schema::hasColumn('company_settings', 'customer_auth_methods')) {
                $table->json('customer_auth_methods')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'team_auth_methods')) {
                $table->json('team_auth_methods')->nullable();
            }

            // ==========================================
            // CAMPOS GOVERNAMENTAIS (Serpro)
            // ==========================================
            if (!Schema::hasColumn('company_settings', 'serpro_client_id')) {
                $table->string('serpro_client_id')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'serpro_client_secret')) {
                $table->string('serpro_client_secret')->nullable();
            }

            // ==========================================
            // GESTÃO DE CLIENTES (PosSchema)
            // ==========================================
            if (!Schema::hasColumn('company_settings', 'customer_registration_mode')) {
                $table->string('customer_registration_mode')->default('basic')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'require_whatsapp')) {
                $table->boolean('require_whatsapp')->default(true);
            }
            if (!Schema::hasColumn('company_settings', 'require_birth_date')) {
                $table->boolean('require_birth_date')->default(false);
            }
            if (!Schema::hasColumn('company_settings', 'allow_minors')) {
                $table->boolean('allow_minors')->default(true);
            }
            if (!Schema::hasColumn('company_settings', 'customer_can_edit_profile')) {
                $table->boolean('customer_can_edit_profile')->default(true);
            }

            // ==========================================
            // PROGRAMA DE FIDELIDADE (MarketingSchema)
            // ==========================================
            if (!Schema::hasColumn('company_settings', 'loyalty_is_active')) {
                $table->boolean('loyalty_is_active')->default(false);
            }
            if (!Schema::hasColumn('company_settings', 'loyalty_reward_type')) {
                $table->string('loyalty_reward_type')->default('cashback')->nullable();
            }
            if (!Schema::hasColumn('company_settings', 'loyalty_accumulation_rule')) {
                $table->string('loyalty_accumulation_rule')->default('cart_value')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
