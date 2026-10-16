<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Services\Customers\CustomerRegistrationService;
use Exception;

class SocialLoginReceiverController extends Controller
{
    public function magicLogin(Request $request, CustomerRegistrationService $registrationService)
    {
        $encryptedToken = $request->query('token');

        try {
            $payload = json_decode(Crypt::decryptString($encryptedToken), true);

            if (now()->timestamp > $payload['expires_at']) {
                throw new Exception('O link de login expirou. Tente novamente.');
            }

            // Delega para o motor de unificação de clientes
            $guestId = session('guest_customer_id');
            $result = $registrationService->evaluateSocialLogin($payload, $guestId);

            $targetUrl = $payload['return_to'] ?? '/';
            if (empty($targetUrl) || str_contains($targetUrl, '/auth/')) {
                $targetUrl = '/';
            }

            // Se o cliente ainda não vinculou CPF (Âncora necessária para o ecossistema)
            if ($result['status'] === 'requires_cpf') {
                $targetUrl = preg_replace('/([?&])open_upgrade=[^&]+(&|$)/', '$1', $targetUrl);
                $targetUrl = rtrim($targetUrl, '?&');
                $separator = str_contains($targetUrl, '?') ? '&' : '?';
                $targetUrl .= $separator . 'open_upgrade=true';

                return redirect()->to($targetUrl)->with('info', $result['message']);
            }

            if ($result['status'] === 'authenticated') {
                Auth::login($result['user'], true);
                $request->session()->regenerate();
            }

            return redirect()->to($targetUrl)->with('success', $result['message']);

        } catch (Exception $e) {
            Log::error('Falha no Magic Login Social: ' . $e->getMessage());
            return redirect()->to('/')->with('error', $e->getMessage());
        }
    }
}
