<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class FacebookController extends Controller
{
    public function getFacebookUrl(Request $request)
    {
        $tenantHost = $request->query('host', request()->getHost());
        $intent = $request->query('intent', 'customer');
        $returnTo = $request->input('return_to', $request->header('referer', '/'));

        $statePayload = base64_encode(json_encode([
            'host'      => $tenantHost,
            'intent'    => $intent,
            'return_to' => $returnTo,
        ]));

        $redirectUrl = Socialite::driver('facebook')
            ->scopes(['email'])
            ->with(['state' => $statePayload])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $redirectUrl]);
    }

    public function handleFacebookCallback(Request $request)
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();
            $stateDecoded = base64_decode($request->input('state'));
            $stateData = json_decode($stateDecoded, true);

            $tenantHost = $stateData['host'] ?? request()->getHost();
            $intent = $stateData['intent'] ?? 'customer';
            $returnTo = $stateData['return_to'] ?? '/';

            $payload = json_encode([
                'provider'   => 'facebook',
                'id'         => $facebookUser->getId(),
                'email'      => $facebookUser->getEmail(),
                'name'       => $facebookUser->getName(),
                'intent'     => $intent,
                'return_to'  => $returnTo,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            $token = urlencode(Crypt::encryptString($payload));

            // Devolve para o domínio do tenant
            return redirect("https://{$tenantHost}/auth/social/magic-login?token={$token}");

        } catch (\Exception $e) {
            Log::error('Erro no callback do Facebook: ' . $e->getMessage());
            return redirect('/')->with('error', 'Falha ao autenticar com o Facebook.');
        }
    }
}
