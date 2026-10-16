<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\GoogleAuthService;
use App\Services\Customers\CustomerRegistrationService;

class GoogleController extends Controller
{
    public function redirectToGoogle(Request $request)
    {
        $tenantHost = $request->query('host', request()->getHost());
        $intent = $request->query('intent', 'team');
        $returnTo = $request->query('return_to', $request->header('referer', '/'));

        $statePayload = base64_encode(json_encode([
            'host'      => $tenantHost,
            'intent'    => $intent,
            'return_to' => $returnTo,
        ]));

        return Socialite::driver('google')
            ->with(['state' => $statePayload])
            ->redirect();
    }

    public function getGoogleUrl(Request $request)
    {
        $tenantHost = $request->query('host', request()->getHost());
        $intent = $request->query('intent', 'customer');
        $returnTo = $request->input('return_to', $request->header('referer', '/'));

        $statePayload = base64_encode(json_encode([
            'host'      => $tenantHost,
            'intent'    => $intent,
            'return_to' => $returnTo,
        ]));

        $redirectUrl = Socialite::driver('google')
            ->with(['state' => $statePayload])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $redirectUrl]);
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $stateDecoded = base64_decode($request->input('state'));
            $stateData = json_decode($stateDecoded, true);

            $returnTo = '/';
            if (is_array($stateData)) {
                $tenantHost = $stateData['host'];
                $intent = $stateData['intent'] ?? 'team';
                $returnTo = $stateData['return_to'] ?? '/';
            } else {
                $tenantHost = $stateDecoded;
                $intent = 'team';
            }

            if ($tenantHost === 'adm.brasume.com.br') {
                $user = User::where('email', $googleUser->getEmail())->first();

                if (! $user) {
                    return redirect()->route('filament.central.auth.login')->with('error', 'Acesso negado. Administrador do SaaS não encontrado.');
                }

                Auth::login($user);
                return redirect()->to('/admin');
            }

            $payload = json_encode([
                'email'      => $googleUser->getEmail(),
                'name'       => $googleUser->getName(),
                'intent'     => $intent,
                'return_to'  => $returnTo,
                'expires_at' => now()->addMinutes(5)->timestamp
            ]);

            $token = urlencode(Crypt::encryptString($payload));

            if ($intent === 'customer') {
                return redirect("https://{$tenantHost}/auth/google/magic-login-customer?token={$token}");
            }
            return redirect("https://{$tenantHost}/auth/google/magic-login-team?token={$token}");

        } catch (\Exception $e) {
            Log::error('🔥 [GOOGLE OAUTH CALLBACK ERROR] ' . $e->getMessage());
            return redirect('/')->with('error', 'Falha ao autenticar com o Google. O processo foi cancelado.');
        }
    }

    public function magicLoginCustomer(Request $request, GoogleAuthService $authService, CustomerRegistrationService $registrationService)
    {
        $token = $request->query('token');

        try {
            $result = $authService->processCustomerLogin($token, session('guest_customer_id'), $registrationService);

            $targetUrl = $result['return_to'] ?? '/';

            // Higienização de segurança para evitar loops
            if (empty($targetUrl) || str_contains($targetUrl, '/auth/google')) {
                $targetUrl = '/';
            }

            if ($result['status'] === 'requires_cpf') {
                $targetUrl = preg_replace('/([?&])open_upgrade=[^&]+(&|$)/', '$1', $targetUrl);
                $targetUrl = rtrim($targetUrl, '?&');

                $separator = str_contains($targetUrl, '?') ? '&' : '?';
                $targetUrl .= $separator . 'open_upgrade=true';

                return redirect()->to($targetUrl)->with('info', $result['message']);
            }

            $request->session()->regenerate();
            $request->session()->save();

            return redirect()->to($targetUrl)->with('success', $result['message']);

        } catch (\Exception $e) {
            Log::error('🔥 [ERRO CRÍTICO] Falha no Magic Login B2C: ' . $e->getMessage());
            return redirect()->to('/')->with('error', $e->getMessage());
        }
    }

    public function magicLoginTeam(Request $request, GoogleAuthService $authService)
    {
        $token = $request->query('token');

        try {
            $result = $authService->processTeamLogin($token);
            $request->session()->regenerate();
            $request->session()->save();

            return redirect()->to($result['redirect'] ?? '/team');

        } catch (\Exception $e) {
            Log::error('🛑 [GOOGLE TEAM ERROR] ' . $e->getMessage());
            return redirect()->route('team.login')->with('error', $e->getMessage());
        }
    }
}
