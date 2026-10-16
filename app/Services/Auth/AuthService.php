<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Exception;

class AuthService
{
    /**
     * Autentica no terminal via PIN comparando a hash Bcrypt dos operadores ativos
     */
    public function loginWithPin(string $pin): void
    {
        $activeStaff = User::where('is_active', true)
            ->whereNotNull('pin')
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'gerente', 'manager', 'caixa', 'garcom', 'pos', 'waiter', 'kitchen']);
            })
            ->get();

        $matchedUser = null;

        foreach ($activeStaff as $user) {
            $isHash = str_starts_with($user->pin, '$2y$') || str_starts_with($user->pin, '$2a$');

            if ($isHash ? Hash::check($pin, $user->pin) : ($user->pin === $pin)) {
                $matchedUser = $user;
                break;
            }
        }

        if (!$matchedUser) {
            throw new Exception('PIN incorreto ou operador não autorizado para operar terminais.');
        }

        Auth::guard('team')->login($matchedUser, true);
        Auth::guard('web')->login($matchedUser, true);
    }

    public function loginWithEmail(string $email, string $password): void
    {
        $user = User::where('email', $email)
            ->where('is_active', true)
            ->whereNotNull('employee_id')
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            Auth::guard('team')->login($user, true);
            Auth::guard('web')->login($user, true);
            return;
        }

        throw new Exception('Credenciais inválidas ou acesso não autorizado para a operação.');
    }
}
