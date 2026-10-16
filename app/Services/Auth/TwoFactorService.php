<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\CompanySetting;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        protected Google2FA $google2fa
    ) {}

    /**
     * Gera os dados necessários para o primeiro acesso (QR Code e Segredo).
     */
    public function getSetupData(User $user): array
    {
        // Gera um segredo único e guarda temporariamente na sessão
        $secret = $this->google2fa->generateSecretKey();
        session(['temp_2fa_secret' => $secret]);

        $companyName = CompanySetting::getCached()?->totp_issuer_name ?? 'Brasume ERP';

        // Retorna a URL no formato "otpauth://totp/..."
        $qrCodeUrl = $this->google2fa->getQRCodeUrl($companyName, $user->email, $secret);

        return [
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl
        ];
    }

    /**
     * Valida o código numérico e, se for a primeira vez, salva o segredo no utilizador.
     */
    public function verifyAndSave(User $user, string $code): bool
    {
        // Recupera o segredo definitivo da base de dados ou o temporário do setup
        $secret = $user->two_factor_secret ?? session('temp_2fa_secret');

        if (!$secret) {
            return false;
        }

        // Valida o código informado contra o relógio do servidor
        $isValid = $this->google2fa->verifyKey($secret, $code);

        if ($isValid) {
            // Se passou na validação e era a primeira vez, oficializa o segredo
            if (empty($user->two_factor_secret)) {
                $user->update([
                    'two_factor_secret' => $secret,
                    'two_factor_confirmed_at' => now()
                ]);
                session()->forget('temp_2fa_secret');
            }

            return true;
        }

        return false;
    }
}
