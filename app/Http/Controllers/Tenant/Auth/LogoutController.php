<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Str;

class LogoutController extends Controller
{
    /**
     * Destrói a sessão de qualquer tipo de usuário (Cliente, KDS ou Equipe).
     */
    public function destroy(Request $request)
    {
        $isTeam = Auth::guard('team')->check();
        $previousUrl = url()->previous();

        // 1. O colaborador (Caixa, KDS, Garçom/Equipe) está saindo?
        if ($isTeam) {
            Auth::guard('team')->logout();
        }

        // 2. O cliente B2C (Cardápio) ou Admin está saindo?
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        // 3. Remove identificadores voláteis de visitantes
        $request->session()->forget([
            'guest_customer_id',
            'guest_addresses',
            'social_registration'
        ]);

        // 4. Invalida a sessão inteira e regenera o token CSRF
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 5. Redirecionamento inteligente com base no terminal de origem
        $redirectUrl = '/customer/home';

        if ($isTeam) {
            if (Str::contains($previousUrl, ['/pos', '/caixa', '/pdv'])) {
                $redirectUrl = '/pos/login';
            } elseif (Str::contains($previousUrl, ['/team/app', '/team/mobile', '/waiter', '/garcom'])) {
                $redirectUrl = '/waiter/login';
            } elseif (Str::contains($previousUrl, ['/kds', '/cozinha'])) {
                $redirectUrl = '/kds/login';
            } else {
                $redirectUrl = '/team/login';
            }
        }

        return Inertia::location($redirectUrl);
    }
}
