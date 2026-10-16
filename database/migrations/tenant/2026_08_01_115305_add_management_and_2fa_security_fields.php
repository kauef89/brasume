<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configurações Globais da Empresa
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('management_auth_methods')->nullable()->after('kds_auth_methods');
            $table->boolean('require_2fa')->default(false)->after('management_auth_methods');
            $table->string('two_factor_method')->nullable()->after('require_2fa');

            // Credenciais das APIs de 2FA
            $table->string('totp_issuer_name')->nullable()->after('two_factor_method');
            $table->text('wa_provider_token')->nullable()->after('totp_issuer_name');
            $table->string('wa_provider_url')->nullable()->after('wa_provider_token');
        });

        // 2. Colunas Individuais do Google Authenticator para os Gestores
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'management_auth_methods', 'require_2fa', 'two_factor_method',
                'totp_issuer_name', 'wa_provider_token', 'wa_provider_url'
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'
            ]);
        });
    }
};
