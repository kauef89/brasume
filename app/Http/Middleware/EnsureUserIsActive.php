<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['web', 'team'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                $employeeInactive = $user?->employee && $user->employee->is_active === false;
                $userInactive = $user && $user->is_active === false;

                if ($userInactive || $employeeInactive) {
                    Auth::guard('web')->logout();
                    Auth::guard('team')->logout();

                    if ($request->hasSession()) {
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                    }

                    if ($request->expectsJson() || $request->header('X-Inertia')) {
                        return response()->json([
                            'message' => 'Seu acesso foi desativado pela administração.',
                        ], 401);
                    }

                    $loginRoute = $request->is('admin*')
                        ? (in_array($request->getHost(), config('tenancy.central_domains', []))
                            ? route('filament.central.auth.login')
                            : route('filament.store.auth.login'))
                        : route('team.login');

                    return redirect()->to($loginRoute)
                        ->with('error', 'Seu acesso foi desativado pela administração.');
                }
            }
        }

        return $next($request);
    }
}
