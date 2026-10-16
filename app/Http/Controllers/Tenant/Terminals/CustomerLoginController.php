<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Customers\CustomerRegistrationService;

class CustomerLoginController extends Controller
{
    public function login(Request $request, CustomerRegistrationService $registrationService)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();
            $registrationService->ensureUserHasCustomerProfile(Auth::user());

            // 🚀 CORREÇÃO: Redirecionamento blindado! Ignora qualquer lixo na sessão e vai pra Home.
            return redirect()->route('customer.app', ['tab' => 'home'])->with('success', 'Bem-vindo de volta!');
        }

        return back()->withErrors(['email' => 'As credenciais fornecidas não correspondem aos nossos registros.']);
    }
}
