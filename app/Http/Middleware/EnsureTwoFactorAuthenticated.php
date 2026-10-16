<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Auth;

class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Ignora se não for gestor/admin ou se não estiver autenticado
        if (!$user || !$user->hasAnyRole(['admin', 'manager', 'gerente'])) {
            return $next($request);
        }

        $settings = CompanySetting::getCached();

        // Se o 2FA for exigido pela empresa e for do tipo Google Auth
        if ($settings && $settings->require_2fa && $settings->two_factor_method === 'google_auth') {

            // Se a sessão atual ainda não passou pelo desafio do 2FA
            if (!session('2fa_verified', false)) {

                // Evita loop infinito de redirecionamento
                if (!$request->is('auth/2fa*')) {
                    return redirect()->route('auth.2fa.challenge');
                }
            }
        }

        return $next($request);
    }
}
