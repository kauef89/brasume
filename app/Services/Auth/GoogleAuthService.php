<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Services\Customers\CustomerRegistrationService;
use Exception;

class GoogleAuthService
{
    /**
     * 🚀 Recebe o Token do Google, decodifica e delega a identidade para o Motor Universal de Clientes
     */
    public function processCustomerLogin(string $encryptedToken, ?int $guestId, CustomerRegistrationService $registrationService): array
    {
        $payload = $this->decodeAndValidateToken($encryptedToken);

        // Avalia o login sem persistência prematura
        $result = $registrationService->evaluateSocialLogin($payload, $guestId);

        // Só efetua login se o usuário já possuir cadastro completo com CPF
        if ($result['status'] === 'authenticated') {
            Auth::login($result['user'], true);
        }

        return [
            'status'    => $result['status'],
            'message'   => $result['message'],
            'return_to' => $payload['return_to'] ?? '/',
        ];
    }

    /**
     * 👔 Processa o login da equipe (B2B) validando os cargos
     */
    public function processTeamLogin(string $encryptedToken): array
    {
        $payload = $this->decodeAndValidateToken($encryptedToken);
        $user = User::where('email', $payload['email'])->first();

        if (!$user) {
            throw new Exception('Acesso negado. Usuário não registrado na equipe.');
        }

        if (!$user->is_active) {
            throw new Exception('Acesso negado. Colaborador inativo.');
        }

        $intent = $payload['intent'] ?? 'team';

        if ($intent === 'admin') {
            Auth::guard('web')->login($user, true);
            return ['guard' => 'web', 'redirect' => '/admin'];
        }

        if (!$user->hasAnyRole(['admin', 'gerente', 'manager', 'caixa', 'garcom', 'pos', 'waiter', 'motoboy', 'driver', 'kitchen'])) {
            throw new Exception('Acesso negado. Perfil de colaborador incorreto.');
        }

        // 🚀 Autentica tanto no guard padrão (web) quanto no team com lembrete ativo, espelhando o PinAuthController
        Auth::guard('web')->login($user, true);
        Auth::guard('team')->login($user, true);

        return ['guard' => 'web', 'redirect' => '/team'];
    }

    /**
     * 🔒 Decodifica a string gerada no Callback central e verifica validade
     */
    private function decodeAndValidateToken(string $encryptedToken): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($encryptedToken), true);
        } catch (Exception $e) {
            throw new Exception('Link de login inválido ou corrompido.');
        }

        if (now()->timestamp > $payload['expires_at']) {
            throw new Exception('O link de login expirou. Tente novamente.');
        }

        return $payload;
    }
}
