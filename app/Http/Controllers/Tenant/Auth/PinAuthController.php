<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\Auth\AuthService;
use App\Models\User;

class PinAuthController extends Controller
{
    public function login(Request $request, AuthService $authService)
    {
        $request->validate(['pin' => 'required|string|min:4']);

        $email = trim($request->email ?? '');
        $pin = trim($request->pin ?? '');
        $isFixedTerminal = $request->filled('device_uuid');
        $isFixedTerminal = $request->filled('device_uuid');

        try {
            if ($request->filled('email')) {
                $user = User::where('email', $email)->first();

                if (!$user || !$user->is_active) {
                    return back()->withErrors(['pin' => 'Colaborador não localizado ou inativo.']);
                }

                // Validação estrita: exige que a coluna contenha uma hash segura
                if (!Hash::check($pin, $user->pin)) {
                    return back()->withErrors(['pin' => 'PIN incorreto. Tente novamente.']);
                }

                Auth::guard('team')->login($user, !$isFixedTerminal);
                if (!$isFixedTerminal) {
                    Auth::guard('web')->login($user, true);
                }
            } else {
                $authService->loginWithPin($pin, !$isFixedTerminal);
            }

            $request->session()->regenerate();
            $request->session()->put('pos_just_logged_in', true);
            return redirect()->intended('/team');

        } catch (\Exception $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }
    }

    public function loginWithEmail(Request $request, AuthService $authService)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string'
        ]);

        try {
            $authService->loginWithEmail($credentials['email'], $credentials['password']);
            $request->session()->regenerate();

            return redirect()->intended('/team');
        } catch (\Exception $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }
    }
}
