<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    public function redirectToGoogle(Request $request)
    {
        // Pega o domínio de origem (ex: nativa.brasume.com.br ou app.brasume.com.br)
        $tenantHost = $request->query('host', request()->getHost());

        return Socialite::driver('google')
            ->with(['state' => base64_encode($tenantHost)]) 
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $tenantHost = base64_decode($request->input('state'));

            // =========================================================
            // LÓGICA 1: PAINEL CENTRAL (O Hub de Gestão)
            // =========================================================
            if ($tenantHost === 'app.brasume.com.br') {
                $user = User::where('email', $googleUser->getEmail())->first();

                if (! $user) {
                    return redirect('/hub/login')->with('error', 'Acesso negado. Utilizador não registado no sistema central.');
                }

                Auth::login($user);
                return redirect('/hub'); 
            }

            // =========================================================
            // LÓGICA 2: PAINEL DAS LOJAS / TENANTS
            // =========================================================
            // Se o código chegou aqui, é porque o destino é uma Lanchonete.
            // Guardamos os dados no cache e geramos um Token de Uso Único.
            $token = Str::random(40);
            
            Cache::put("google_auth_{$token}", [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
            ], now()->addMinutes(2));

            // Redireciona o utilizador de volta para o domínio da lanchonete dele!
            return redirect("https://{$tenantHost}/auth/google/magic-login?token={$token}");

        } catch (\Exception $e) {
            // Em caso de erro ou cancelamento
            return redirect('/login')->with('error', 'Falha ao autenticar com o Google. O processo foi cancelado.');
        }
    }

    public function magicLogin(Request $request)
    {
        $token = $request->query('token');

        // Puxa os dados do cache
        $googleData = Cache::get("google_auth_{$token}");

        if (! $googleData) {
            return redirect('/admin/login')->with('error', 'Link de login expirado ou inválido.');
        }

        // Apaga o token do cache imediatamente por segurança
        Cache::forget("google_auth_{$token}");

        // NESTE PONTO: O Stancl Tenancy JÁ INICIALIZOU a base de dados da Lanchonete!
        // Apenas utilizadores que já foram registados nesta loja podem entrar.
        $user = User::where('email', $googleData['email'])->first();

        if (! $user) {
            return redirect('/admin/login')->with('error', 'Acesso negado. O seu email não está registado nesta loja.');
        }

        // Faz o login no contexto da loja isolada
        Auth::login($user);

        // Leva-o para o dashboard da loja
        return redirect('/admin');
    }
}